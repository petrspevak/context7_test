<?php

namespace OurCms\ShopBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use OurCms\ShopBundle\Repository\CartRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'our:shop:clear-old-carts',
    description: 'Deletes shopping carts older than one month.',
)]
class ClearOldCartsCommand extends Command
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Mazání starých nákupních košíků');

        $threshold = new \DateTimeImmutable('-1 month');
        $io->note('Hledám košíky starší než: ' . $threshold->format('Y-m-d H:i:s'));

        $oldCarts = $this->cartRepository->findOld($threshold);

        if (empty($oldCarts)) {
            $io->success('Nebyly nalezeny žádné staré košíky ke smazání.');
            return Command::SUCCESS;
        }

        $count = count($oldCarts);
        $io->info(sprintf('Nalezeno %d starých košíků ke smazání.', $count));

        $io->progressStart($count);

        foreach ($oldCarts as $cart) {
            $this->entityManager->remove($cart);
            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        $io->success(sprintf('Úspěšně smazáno %d starých nákupních košíků.', $count));

        return Command::SUCCESS;
    }
}
