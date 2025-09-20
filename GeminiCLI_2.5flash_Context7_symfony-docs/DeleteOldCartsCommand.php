<?php

namespace OurCms\ShopBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use OurCms\ShopBundle\Entity\Cart; // Assuming Cart entity is in this namespace

#[AsCommand(
    name: 'app:delete-old-carts',
    description: 'Deletes shopping carts older than one month.',
)]
class DeleteOldCartsCommand extends Command
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

        $oneMonthAgo = new \DateTime('-1 month');

        $cartRepository = $this->entityManager->getRepository(Cart::class);

        $oldCarts = $cartRepository->createQueryBuilder('c')
            ->where('c.createdAt < :oneMonthAgo')
            ->setParameter('oneMonthAgo', $oneMonthAgo)
            ->getQuery()
            ->getResult();

        $deletedCount = 0;
        foreach ($oldCarts as $cart) {
            $this->entityManager->remove($cart);
            $deletedCount++;
        }

        $this->entityManager->flush();

        if ($deletedCount > 0) {
            $io->success(sprintf('Successfully deleted %d old shopping cart(s).', $deletedCount));
        } else {
            $io->info('No old shopping carts found to delete.');
        }

        return Command::SUCCESS;
    }
}
