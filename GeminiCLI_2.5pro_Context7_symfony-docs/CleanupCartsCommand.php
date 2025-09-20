<?php

namespace OurCms\ShopBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use OurCms\ShopBundle\Entity\Cart;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'our:shop:cleanup-carts',
    description: 'Deletes carts older than one month.',
)]
class CleanupCartsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $oneMonthAgo = new \DateTimeImmutable('-1 month');

        $io->note(sprintf('Looking for carts older than %s...', $oneMonthAgo->format('Y-m-d H:i:s')));

        $cartRepository = $this->entityManager->getRepository(Cart::class);
        $queryBuilder = $cartRepository->createQueryBuilder('c');
        $queryBuilder
            ->where('c.updatedAt < :date')
            ->setParameter('date', $oneMonthAgo);

        $oldCarts = $queryBuilder->getQuery()->getResult();

        if (empty($oldCarts)) {
            $io->success('No old carts to delete.');
            return Command::SUCCESS;
        }

        $count = count($oldCarts);
        $io->note(sprintf('Found %d carts to delete.', $count));

        foreach ($oldCarts as $cart) {
            $this->entityManager->remove($cart);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Successfully deleted %d old carts.', $count));

        return Command::SUCCESS;
    }
}
