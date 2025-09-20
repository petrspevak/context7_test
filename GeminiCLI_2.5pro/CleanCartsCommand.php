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
    name: 'ourcms:shop:clean-carts',
    description: 'Deletes carts older than one month.',
)]
class CleanCartsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to delete carts that have not been updated for more than a month.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $oneMonthAgo = new \DateTime('-1 month');
        $qb = $this->entityManager->createQueryBuilder();

        $qb->delete(Cart::class, 'c')
            ->where('c.updatedAt < :date')
            ->setParameter('date', $oneMonthAgo);

        $numDeleted = $qb->getQuery()->execute();

        $io->success(sprintf('Successfully deleted %d old cart(s).', $numDeleted));

        return Command::SUCCESS;
    }
}
