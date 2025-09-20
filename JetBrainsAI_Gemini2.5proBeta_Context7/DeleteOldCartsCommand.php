<?php

namespace App\ShopBundle\Command;

use App\ShopBundle\Entity\Cart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:delete-old-carts',
    description: 'Deletes carts older than one month.',
)]
class DeleteOldCartsCommand extends Command
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
            ->setDescription('Deletes carts older than one month.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $oneMonthAgo = new \DateTime('-1 month');

        $query = $this->entityManager->createQuery(
            'SELECT c FROM App\ShopBundle\Entity\Cart c WHERE c.createdAt < :date'
        )->setParameter('date', $oneMonthAgo);

        $oldCarts = $query->getResult();

        if (empty($oldCarts)) {
            $io->success('No old carts to delete.');
            return Command::SUCCESS;
        }

        foreach ($oldCarts as $cart) {
            $this->entityManager->remove($cart);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Successfully deleted %d old carts.', count($oldCarts)));

        return Command::SUCCESS;
    }
}
