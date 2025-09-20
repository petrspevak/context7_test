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
     * @param DateTime $date
     * @return Cart[]
     */
    public function findOlderThan(DateTime $date): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }
}