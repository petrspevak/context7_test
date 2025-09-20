<?php

namespace OurCMS\ShopBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use OurCMS\ShopBundle\Repository\CartRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ourcms:shop:cleanup-carts',
    description: 'Deletes carts older than one month.',
)]
class CleanupCartsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CartRepository $cartRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Cleaning up old shopping carts');

        $limitDate = new \DateTime('-1 month');
        $io->writeln('Looking for carts older than ' . $limitDate->format('Y-m-d H:i:s'));

        $oldCarts = $this->cartRepository->findCartsOlderThan($limitDate);

        if (empty($oldCarts)) {
            $io->success('No old carts found to delete.');
            return Command::SUCCESS;
        }

        $count = count($oldCarts);
        $io->progressStart($count);

        foreach ($oldCarts as $cart) {
            $this->entityManager->remove($cart);
            $io->progressAdvance();
        }

        $this->entityManager->flush();

        $io->progressFinish();
        $io->success(sprintf('Successfully deleted %d old cart(s).', $count));

        return Command::SUCCESS;
    }
}
