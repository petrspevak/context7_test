<?php

namespace OurCMS\ShopBundle\Command;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use OurCMS\ShopBundle\Repository\CartRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'shop:clean-old-carts',
    description: 'Smaže nákupní košíky starší než měsíc',
)]
class CleanOldCartsCommand extends Command
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

        // Vypočítáme datum před měsícem
        $oneMonthAgo = new DateTime();
        $oneMonthAgo->modify('-1 month');

        $io->info(sprintf('Hledám košíky starší než %s', $oneMonthAgo->format('Y-m-d H:i:s')));

        // Najdeme staré košíky
        $oldCarts = $this->cartRepository->findOlderThan($oneMonthAgo);
        $count = count($oldCarts);

        if ($count === 0) {
            $io->success('Nebyly nalezeny žádné staré košíky k smazání.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Nalezeno %d starých košíků k smazání', $count));

        // Smažeme košíky
        foreach ($oldCarts as $cart) {
            $this->entityManager->remove($cart);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Úspěšně smazáno %d starých nákupních košíků.', $count));

        return Command::SUCCESS;
    }
}
