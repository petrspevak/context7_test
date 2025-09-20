<?php

namespace OurCMS\ShopBundle\Command;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use OurCMS\ShopBundle\Repository\CartRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'shop:clean-old-carts',
    description: 'Smaže nákupní košíky starší než zadaný počet dní (výchozí: 30 dní)'
)]
class CleanOldCartsCommand extends Command
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Počet dní, po kterých se košíky považují za staré (výchozí: 30)',
                30
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Pouze zobrazí počet košíků k smazání bez skutečného smazání'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');

        if ($days <= 0) {
            $io->error('Počet dní musí být kladné číslo.');
            return Command::FAILURE;
        }

        // Vypočítej datum hranice (před X dny)
        $cutoffDate = new DateTime();
        $cutoffDate->modify("-{$days} days");

        $io->info(sprintf(
            'Hledám nákupní košíky starší než %d dní (před %s)...',
            $days,
            $cutoffDate->format('Y-m-d H:i:s')
        ));

        // Najdi staré košíky
        $oldCarts = $this->cartRepository->findOldCarts($cutoffDate);
        $count = count($oldCarts);

        if ($count === 0) {
            $io->success('Nebyly nalezeny žádné staré nákupní košíky k smazání.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Nalezeno %d starých nákupních košíků.', $count));

        if ($dryRun) {
            $io->warning('DRY RUN REŽIM - žádné košíky nebudou skutečně smazány.');

            // Zobraz seznam košíků k smazání
            $tableRows = [];
            foreach ($oldCarts as $cart) {
                $tableRows[] = [
                    $cart->getId(),
                    $cart->getHash(),
                    $cart->getCustomer()?->getEmail() ?? 'Anonymní',
                    $cart->getCreatedAt()?->format('Y-m-d H:i:s'),
                    count($cart->getProducts())
                ];
            }

            $io->table(
                ['ID', 'Hash', 'Zákazník', 'Vytvořen', 'Počet produktů'],
                $tableRows
            );

            return Command::SUCCESS;
        }

        // Dotaz na potvrzení
        if (!$io->confirm(sprintf('Opravdu chcete smazat %d starých nákupních košíků?', $count))) {
            $io->info('Operace zrušena.');
            return Command::SUCCESS;
        }

        // Smaž košíky
        $deletedCount = 0;
        foreach ($oldCarts as $cart) {
            try {
                $this->entityManager->remove($cart);
                $deletedCount++;
            } catch (\Exception $e) {
                $io->warning(sprintf(
                    'Nepodařilo se smazat košík ID %d: %s',
                    $cart->getId(),
                    $e->getMessage()
                ));
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Úspěšně smazáno %d z %d starých nákupních košíků.',
            $deletedCount,
            $count
        ));

        return Command::SUCCESS;
    }
}
