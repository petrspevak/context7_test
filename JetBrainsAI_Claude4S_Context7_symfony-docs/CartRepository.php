<?php

namespace OurCMS\ShopBundle\Repository;

use DateTime;
use OurCMS\BaseBundle\Repository\BaseRepository;
use OurCMS\ShopBundle\Entity\Cart;

class CartRepository extends BaseRepository
{
    /**
     * Najde všechny košíky starší než zadané datum
     *
     * @param DateTime $cutoffDate Datum hranice - košíky starší než toto datum budou vráceny
     * @return Cart[] Pole starých košíků
     */
    public function findOldCarts(DateTime $cutoffDate): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Spočítá počet košíků starších než zadané datum
     *
     * @param DateTime $cutoffDate Datum hranice
     * @return int Počet starých košíků
     */
    public function countOldCarts(DateTime $cutoffDate): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getSingleScalarResult();
    }
}