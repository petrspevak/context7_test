<?php

namespace OurCMS\ShopBundle\Command;

use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OurCMS\ShopBundle\Entity\Cart;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'shop:cart:prune',
    description: 'Smaže nákupní košíky (Cart) starší než zadaný počet dnů (default 31).'
)]
class PruneOldCartsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Počet dnů stáří košíku pro smazání',
                31
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Pouze ukázat, kolik košíků by bylo smazáno, bez provedení mazání'
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Velikost dávky pro mazání (kvůli paměti)',
                200
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int)$input->getOption('days');
        if ($days <= 0) {
            $io->error('Hodnota --days musí být kladné celé číslo.');
            return Command::INVALID;
        }

        $batchSize = max(1, (int)$input->getOption('batch-size'));
        $dryRun = (bool)$input->getOption('dry-run');

        $cutoff = (new DateTimeImmutable())->sub(new DateInterval('P' . $days . 'D'));

        // Spočítat kolik záznamů by se smazalo
        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Cart::class, 'c')
            ->where('c.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff);

        $toDelete = (int)$countQb->getQuery()->getSingleScalarResult();

        if ($toDelete === 0) {
            $io->success(sprintf('Nebyly nalezeny žádné košíky starší než %d dnů (do %s).', $days, $cutoff->format('Y-m-d H:i:s')));
            return Command::SUCCESS;
        }

        $io->info(sprintf('Nalezeno %d košíků starších než %d dnů (do %s).', $toDelete, $days, $cutoff->format('Y-m-d H:i:s')));

        if ($dryRun) {
            $io->warning('Režim dry-run: neproběhne žádné mazání.');
            return Command::SUCCESS;
        }

        $deleted = 0;

        // Mažeme po dávkách kvůli paměti
        do {
            $batch = $this->em->createQueryBuilder()
                ->select('c')
                ->from(Cart::class, 'c')
                ->where('c.createdAt < :cutoff')
                ->setParameter('cutoff', $cutoff)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();

            $batchCount = count($batch);
            if ($batchCount === 0) {
                break;
            }

            foreach ($batch as $cart) {
                $this->em->remove($cart);
            }

            $this->em->flush();
            $this->em->clear(); // uvolnit paměť – detach všech entit

            $deleted += $batchCount;

            $io->progressAdvance($batchCount);
        } while (true);

        $io->newLine(2);
        $io->success(sprintf('Smazáno %d košíků starších než %d dnů.', $deleted, $days));

        return Command::SUCCESS;
    }
}
