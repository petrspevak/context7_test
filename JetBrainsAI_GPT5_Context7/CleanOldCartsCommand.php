<?php

namespace OurCMS\ShopBundle\Command;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use OurCMS\ShopBundle\Entity\Cart;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'shop:cart:cleanup',
    description: 'Deletes shopping carts older than a given threshold (default: older than 1 month)'
)]
class CleanOldCartsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'older-than',
                null,
                InputOption::VALUE_REQUIRED,
                'Relative threshold parsable by DateTime (e.g. "-30 days", "-1 month", "-6 weeks")',
                '-1 month'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Only show what would be deleted without actually deleting'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $relative = (string)$input->getOption('older-than') ?: '-1 month';

        try {
            $threshold = new DateTimeImmutable($relative);
        } catch (\Exception $e) {
            $io->error(sprintf('Invalid "older-than" value "%s": %s', $relative, $e->getMessage()));
            return Command::FAILURE;
        }

        $now = new DateTimeImmutable();
        if ($threshold >= $now) {
            $io->error(sprintf(
                'Threshold "%s" must be in the past. Now: %s',
                $threshold->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s')
            ));
            return Command::FAILURE;
        }

        $dryRun = (bool)$input->getOption('dry-run');
        $io->title('Cleaning old shopping carts');
        $io->text(sprintf('Threshold: %s (older than)', $threshold->format('Y-m-d H:i:s')));
        if ($dryRun) {
            $io->comment('Dry-run enabled: no data will be deleted.');
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Cart::class, 'c')
            ->where('c.updatedAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('c.id', 'ASC');

        $query = $qb->getQuery();

        $batchSize = 100;
        $processed = 0;
        $deletedIds = [];

        // Iterate results to avoid loading all entities into memory
        foreach ($query->toIterable([], Query::HYDRATE_OBJECT) as $cart) {
            if (!$cart instanceof Cart) {
                continue;
            }

            if ($dryRun) {
                $deletedIds[] = sprintf('#%d (updatedAt: %s)', $cart->getId(), $cart->getUpdatedAt()?->format('Y-m-d H:i:s') ?? 'NULL');
            } else {
                $this->entityManager->remove($cart);
                if ((++$processed % $batchSize) === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
            }

            if ($dryRun) {
                $processed++;
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        if ($dryRun) {
            $io->success(sprintf('%d carts would be deleted.', $processed));
            if ($processed > 0) {
                $io->section('Example of carts to be deleted:');
                $io->listing(array_slice($deletedIds, 0, 50));
                if ($processed > 50) {
                    $io->text(sprintf('...and %d more', $processed - 50));
                }
            }
        } else {
            $io->success(sprintf('Deleted %d carts older than %s.', $processed, $threshold->format('Y-m-d H:i:s')));
        }

        return Command::SUCCESS;
    }
}
