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
    name: 'shop:clean-up-carts',
    description: 'Deletes carts older than one month.',
)]
class CleanUpCartsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setHelp('This command allows you to delete carts that have not been updated for over a month.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $oneMonthAgo = new \DateTime('-1 month');
        $cartRepository = $this->entityManager->getRepository(Cart::class);

        $queryBuilder = $cartRepository->createQueryBuilder('c');
        $queryBuilder->where('c.updatedAt < :date')
            ->setParameter('date', $oneMonthAgo);

        $cartsToDelete = $queryBuilder->getQuery()->getResult();

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
