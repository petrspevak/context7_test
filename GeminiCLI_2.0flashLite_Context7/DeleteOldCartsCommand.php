<?php

namespace OurcmsShopBundle\Command;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OurcmsShopBundle\Entity\Cart;

#[AsCommand(
    name: 'ourcms:shop:delete-old-carts',
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

    protected function execute(InputInterface $input, OutputInterface $output):
    {
        $cutoffDate = new DateTime('-1 month');

        $carts = $this->entityManager->getRepository(Cart::class)->findBy([
            'createdAt' => ['$lt' => $cutoffDate]
        ]);

        foreach ($carts as $cart) {
            $this->entityManager->remove($cart);
        }

        $this->entityManager->flush();

        $output->writeln(sprintf('Deleted %d old carts.', count($carts)));

        return Command::SUCCESS;
    }
}
