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
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Pouze zobrazí košíky, které by byly smazány bez jejich skutečného smazání'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Potvrzuje smazání bez interaktivního dotazu'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Mazání starých nákupních košíků');

        // Vypočítej datum před jedním měsícem
        $oneMonthAgo = new DateTime();
        $oneMonthAgo->modify('-1 month');

        $io->info(sprintf('Hledám košíky starší než: %s', $oneMonthAgo->format('d.m.Y H:i:s')));

        // Najdi staré košíky
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('c')
            ->from(Cart::class, 'c')
            ->where('c.createdAt < :oneMonthAgo')
            ->setParameter('oneMonthAgo', $oneMonthAgo);

        $oldCarts = $queryBuilder->getQuery()->getResult();
        $count = count($oldCarts);

        if ($count === 0) {
            $io->success('Nenalezeny žádné košíky starší než jeden měsíc.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('Nalezeno %d košíků starších než jeden měsíc.', $count));

        // Dry-run režim
        if ($input->getOption('dry-run')) {
            $io->section('Košíky, které by byly smazány (dry-run):');
            
            foreach ($oldCarts as $cart) {
                $io->text(sprintf(
                    '- ID: %d, Vytvořen: %s, Zákazník: %s',
                    $cart->getId(),
                    $cart->getCreatedAt()?->format('d.m.Y H:i:s'),
                    $cart->getCustomer() ? $cart->getCustomer()->getEmail() : 'Nepřihlášený'
                ));
            }
            
            $io->info('Dry-run režim - žádné košíky nebyly skutečně smazány.');
            return Command::SUCCESS;
        }

        // Potvrzení před smazáním
        if (!$input->getOption('force')) {
            if (!$io->confirm(sprintf('Opravdu chcete smazat %d košíků?', $count), false)) {
                $io->info('Operace zrušena.');
                return Command::SUCCESS;
            }
        }

        // Smaž košíky
        $io->progressStart($count);
        $deletedCount = 0;

        try {
            foreach ($oldCarts as $cart) {
                $this->entityManager->remove($cart);
                $deletedCount++;
                $io->progressAdvance();
                
                // Flush po každých 100 košících pro lepší výkon
                if ($deletedCount % 100 === 0) {
                    $this->entityManager->flush();
                }
            }
            
            // Finální flush pro zbývající košíky
            $this->entityManager->flush();
            $io->progressFinish();
            
            $io->success(sprintf('Úspěšně smazáno %d košíků starších než jeden měsíc.', $deletedCount));
            
        } catch (\Exception $e) {
            $io->progressFinish();
            $io->error(sprintf('Chyba při mazání košíků: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}