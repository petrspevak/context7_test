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
    name: 'app:delete-old-carts',
    description: 'Deletes shopping carts older than one month.',
)]
class DeleteOldCartsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
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

        $io->success(sprintf('Deleted %d shopping cart(s) older than one month.', $deletedCount));

        return Command::SUCCESS;
    }
}
