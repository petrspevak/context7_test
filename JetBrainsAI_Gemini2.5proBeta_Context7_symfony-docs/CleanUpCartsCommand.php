<?php

namespace App\ShopBundle\Command;

use App\ShopBundle\Entity\Cart;
use Doctrine\ORM\EntityManagerInterface;
use OurCMS\ShopBundle\Entity\Cart;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'shop:carts:cleanup',
    description: 'Deletes carts older than one month.',
)]
class CleanUpCartsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $oneMonthAgo = new \DateTime('-1 month');

        $io->note(sprintf('Deleting carts older than %s...', $oneMonthAgo->format('Y-m-d H:i:s')));

        $cartRepository = $this->entityManager->getRepository(Cart::class);
        $qb = $cartRepository->createQueryBuilder('c');
        $qb->where('c.createdAt < :date')
            ->setParameter('date', $oneMonthAgo);

        $cartsToDelete = $qb->getQuery()->getResult();

        if (empty($cartsToDelete)) {
            $io->success('No old carts to delete.');
            return Command::SUCCESS;
        }

        foreach ($cartsToDelete as $cart) {
            $this->entityManager->remove($cart);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Successfully deleted %d old carts.', count($cartsToDelete)));

        return Command::SUCCESS;
    }
}
