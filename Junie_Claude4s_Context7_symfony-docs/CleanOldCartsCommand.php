<?php

namespace OurCMS\ShopBundle\Command;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use OurCMS\ShopBundle\Entity\Cart;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'shop:clean-old-carts',
    description: 'Smaže nákupní košíky starší než jeden měsíc'
)]
class CleanOldCartsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Smaže nákupní košíky starší než jeden měsíc')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Zobrazí košíky, které by byly smazány, ale nesmaže je'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Potvrdí smazání bez interaktivního dotazu'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Čištění starých nákupních košíků');

        // Vypočítej datum před měsícem
        $oneMonthAgo = new DateTime();
        $oneMonthAgo->modify('-1 month');

        $io->info(sprintf('Hledám košíky vytvořené před: %s', $oneMonthAgo->format('d.m.Y H:i:s')));

        // Najdi staré košíky
        $cartRepository = $this->entityManager->getRepository(Cart::class);
        $oldCarts = $cartRepository->createQueryBuilder('c')
            ->where('c.createdAt < :date')
            ->setParameter('date', $oneMonthAgo)
            ->getQuery()
            ->getResult();

        $cartCount = count($oldCarts);

        if ($cartCount === 0) {
            $io->success('Žádné staré košíky k smazání nenalezeny.');
            return Command::SUCCESS;
        }

        $io->note(sprintf('Nalezeno %d košíků ke smazání.', $cartCount));

        // Dry-run mode
        if ($input->getOption('dry-run')) {
            $io->warning('DRY-RUN režim - košíky nebudou smazány');
            
            $tableData = [];
            foreach ($oldCarts as $cart) {
                $tableData[] = [
                    $cart->getId(),
                    $cart->getCreatedAt()?->format('d.m.Y H:i:s') ?? 'N/A',
                    $cart->getCustomer()?->getEmail() ?? 'anonymní',
                    count($cart->getProducts())
                ];
            }

            $io->table(
                ['ID', 'Vytvořen', 'Zákazník', 'Počet produktů'],
                $tableData
            );

            return Command::SUCCESS;
        }

        // Potvrzení smazání
        if (!$input->getOption('force')) {
            if (!$io->confirm(sprintf('Opravdu chcete smazat %d košíků?', $cartCount), false)) {
                $io->info('Operace zrušena.');
                return Command::SUCCESS;
            }
        }

        // Smaž košíky
        $deletedCount = 0;
        foreach ($oldCarts as $cart) {
            try {
                $this->entityManager->remove($cart);
                $deletedCount++;
            } catch (\Exception $e) {
                $io->error(sprintf('Chyba při mazání košíku ID %d: %s', $cart->getId(), $e->getMessage()));
            }
        }

        try {
            $this->entityManager->flush();
            $io->success(sprintf('Úspěšně smazáno %d košíků.', $deletedCount));
        } catch (\Exception $e) {
            $io->error('Chyba při ukládání změn: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}