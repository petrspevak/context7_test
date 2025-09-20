<?php

declare(strict_types=1);

namespace OurCMS\ShopBundle\Command;

use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OurCMS\ShopBundle\Entity\Cart;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'shop:cart:cleanup',
    description: 'Smaže nákupní košíky (Cart) starší než zadaný počet dnů (výchozí 31).'
)]
class CartCleanupCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Kolik dní zpět ponechat (košíky starší budou smazány).', '31')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Pouze vypíše, kolik košíků by bylo smazáno, nic nemaže.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $daysOpt = (string) $input->getOption('days');
        if (!ctype_digit($daysOpt)) {
            $output->writeln('<error>Parametr --days musí být nezáporné celé číslo.</error>');
            return Command::INVALID;
        }
        $days = (int) $daysOpt;
        if ($days < 0) {
            $output->writeln('<error>Parametr --days musí být nezáporné celé číslo.</error>');
            return Command::INVALID;
        }

        $threshold = (new DateTimeImmutable('now'))->sub(new DateInterval('P' . max(0, $days) . 'D'));

        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $count = (int) $this->em->createQuery(
                'SELECT COUNT(c.id) FROM ' . Cart::class . ' c WHERE c.createdAt < :threshold'
            )->setParameter('threshold', $threshold)->getSingleScalarResult();

            $output->writeln(sprintf('Dry-run: %d košíků je starších než %d dní (hranice: %s).', $count, $days, $threshold->format('Y-m-d H:i:s')));
            return Command::SUCCESS;
        }

        // Použijeme DQL bulk DELETE. Díky onDelete: CASCADE na CartProduct.cart FK se odstraní i položky košíku.
        $deleted = $this->em->createQuery(
            'DELETE FROM ' . Cart::class . ' c WHERE c.createdAt < :threshold'
        )->setParameter('threshold', $threshold)->execute();

        $output->writeln(sprintf('Smazáno %d košíků starších než %d dní (hranice: %s).', $deleted, $days, $threshold->format('Y-m-d H:i:s')));

        return Command::SUCCESS;
    }
}
