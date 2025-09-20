<?php

namespace OurCMS\ShopBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use OurCMS\ShopBundle\Entity\Cart;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'shop:prune-carts',
    description: 'Deletes carts older than one month.',
)]
class PruneCartsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command allows you to delete shopping carts that have not been updated for more than a month.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $threshold = new \DateTimeImmutable('-1 month');
        $io->info(sprintf('Pruning carts older than %s.', $threshold->format('Y-m-d H:i:s')));

        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(Cart::class, 'c')
            ->where('c.updatedAt < :threshold')
            ->setParameter('threshold', $threshold);

        $numDeleted = $qb->getQuery()->execute();

        $io->success(sprintf('Successfully deleted %d old cart(s).', $numDeleted));

        return Command::SUCCESS;
    }
}