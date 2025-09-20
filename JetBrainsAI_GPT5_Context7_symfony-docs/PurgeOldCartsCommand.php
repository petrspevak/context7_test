<?php

namespace OurCMS\ShopBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use OurCMS\ShopBundle\Entity\Cart;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ourcms:shop:purge-old-carts',
    description: 'Deletes shopping carts older than the specified number of days (default 31).'
)]
class PurgeOldCartsCommand extends Command
{
    protected static $defaultName = 'ourcms:shop:purge-old-carts';
    protected static $defaultDescription = 'Deletes shopping carts older than the specified number of days (default 31).';

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Delete carts older than N days', 31)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show how many carts would be deleted');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');
        if ($days <= 0) {
            $io->error('Option --days must be a positive integer.');
            return Command::INVALID;
        }

        $threshold = new \DateTimeImmutable(sprintf('-%d days', $days));

        // Determine which date field exists on Cart
        $classMetadata = $this->em->getClassMetadata(Cart::class);
        $dateField = null;
        foreach (['updatedAt', 'createdAt', 'lastActivityAt'] as $candidate) {
            if ($classMetadata->hasField($candidate)) {
                $dateField = $candidate;
                break;
            }
        }

        if ($dateField === null) {
            $io->error('Cart entity does not have a recognizable date field (updatedAt/createdAt/lastActivityAt).');
            return Command::FAILURE;
        }

        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Cart::class, 'c')
            ->where(sprintf('c.%s < :threshold', $dateField))
            ->setParameter('threshold', $threshold);

        // Count first
        $countQb = clone $qb;
        $count = (int) $countQb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        if ($input->getOption('dry-run')) {
            $io->info(sprintf(
                'Dry run: %d cart(s) older than %d day(s) would be deleted (threshold: %s, field: %s).',
                $count,
                $days,
                $threshold->format('Y-m-d H:i:s'),
                $dateField
            ));
            return Command::SUCCESS;
        }

        if ($count === 0) {
            $io->success('No carts to delete.');
            return Command::SUCCESS;
        }

        $io->note(sprintf(
            'Deleting %d cart(s) older than %d day(s) using field "%s" (threshold: %s)...',
            $count,
            $days,
            $dateField,
            $threshold->format('Y-m-d H:i:s')
        ));

        $batchSize = 100;
        $deleted = 0;

        $io->progressStart($count);

        // Iterate and remove to trigger cascade remove properly (vs DQL DELETE which skips cascades)
        $iterable = $qb->getQuery()->toIterable();
        foreach ($iterable as $cart) {
            // $cart is an instance of OurCMS\ShopBundle\Entity\Cart
            $this->em->remove($cart);
            $deleted++;

            if ($deleted % $batchSize === 0) {
                $this->em->flush();
                $this->em->clear();
            }

            $io->progressAdvance(1);
        }

        $this->em->flush();
        $io->progressFinish();

        $io->success(sprintf('Deleted %d cart(s).', $deleted));

        return Command::SUCCESS;
    }
}
