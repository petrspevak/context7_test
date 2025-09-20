<?php

namespace App\ShopBundle\Command;

use App\ShopBundle\Entity\Cart;
use Doctrine\ORM\EntityManagerInterface;
use OurCMS\ShopBundle\Entity\Cart;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteOldCartsCommand extends Command
{
    protected static $defaultName = 'shop:delete-old-carts';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Deletes carts older than one month.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $oneMonthAgo = new \DateTime('-1 month');

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->delete(Cart::class, 'c')
            ->where('c.creationDate < :date')
            ->setParameter('date', $oneMonthAgo);

        $numDeleted = $queryBuilder->getQuery()->execute();

        $io->success(sprintf('Successfully deleted %d old carts.', $numDeleted));

        return Command::SUCCESS;
    }
}
