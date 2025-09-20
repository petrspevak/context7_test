<?php

namespace OurCMS\ShopBundle\Command;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteOldCartsCommand extends Command
{
    protected static $defaultName = 'ourcms:shop:delete-old-carts';
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Deletes shopping carts older than one month.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $date = new DateTime('-1 month');

        $carts = $this->entityManager->getRepository(\OurCMS\ShopBundle\Entity\Cart::class)->findBy(['createdAt' => $date]);

        $deletedCount = 0;
        foreach ($carts as $cart) {
            $this->entityManager->remove($cart);
            $deletedCount++;
        }

        $this->entityManager->flush();

        $io->success(sprintf('Deleted %d old carts.', $deletedCount));

        return Command::SUCCESS;
    }
}
