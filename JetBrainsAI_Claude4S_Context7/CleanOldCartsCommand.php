<?php

namespace OurCMS\ShopBundle\Command;

use DateTime;
use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use OurCMS\ShopBundle\Entity\Cart;
use OurCMS\ShopBundle\Repository\CartRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'shop:clean-old-carts',
    description: 'Smaže nakupní košíky starší než jeden měsíc'
)]
class CleanOldCartsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartRepository $cartRepository
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
                'Pouze zobrazí košíky, které by byly smazány, ale nesmaže je'
            )
            ->addOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Počet dní pro určení starých košíků (výchozí: 30)',
                30
            )
            ->setHelp('Tento příkaz smaže všechny nakupní košíky, které jsou starší než zadaný počet dní.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $days = (int) $input->getOption('days');

        if ($days < 1) {
            $io->error('Počet dní musí být větší než 0');
            return Command::FAILURE;
        }

        $cutoffDate = new DateTime();
        $cutoffDate->sub(new DateInterval('P' . $days . 'D'));

        $io->title('Mazání starých nakupních košíků');
        $io->info(sprintf('Hledám košíky starší než %d dní (před %s)', $days, $cutoffDate->format('Y-m-d H:i:s')));

        // Najdeme staré košíky
        $queryBuilder = $this->cartRepository->createQueryBuilder('c')
            ->where('c.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate);

        $oldCarts = $queryBuilder->getQuery()->getResult();

        if (empty($oldCarts)) {
            $io->success('Nenalezeny žádné staré košíky k smazání.');
            return Command::SUCCESS;
        }

        $count = count($oldCarts);
        $io->info(sprintf('Nalezeno %d starých košíků', $count));

        if ($dryRun) {
            $io->warning('DRY RUN - košíky nebudou skutečně smazány');

            foreach ($oldCarts as $cart) {
                /** @var Cart $cart */
                $io->writeln(sprintf(
                    'Košík ID: %d, Hash: %s, Vytvořen: %s, Produktů: %d',
                    $cart->getId(),
                    $cart->getHash(),
                    $cart->getCreatedAt()?->format('Y-m-d H:i:s') ?? 'N/A',
                    $cart->getProducts()->count()
                ));
            }

            $io->success(sprintf('DRY RUN dokončen. Bylo by smazáno %d košíků.', $count));
            return Command::SUCCESS;
        }

        if (!$io->confirm(sprintf('Opravdu chcete smazat %d košíků?', $count), false)) {
            $io->info('Operace zrušena uživatelem.');
            return Command::SUCCESS;
        }

        $deletedCount = 0;

        try {
            $this->entityManager->beginTransaction();

            foreach ($oldCarts as $cart) {
                $this->entityManager->remove($cart);
                $deletedCount++;

                // Flush po každých 100 záznamech pro lepší výkon
                if ($deletedCount % 100 === 0) {
                    $this->entityManager->flush();
                    $io->writeln(sprintf('Zpracováno %d z %d košíků...', $deletedCount, $count));
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $io->success(sprintf('Úspěšně smazáno %d starých košíků.', $deletedCount));

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $io->error(sprintf('Chyba při mazání košíků: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
