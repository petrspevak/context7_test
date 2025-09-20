<?php

namespace OurCMS\ShopBundle\Repository;

use OurCMS\BaseBundle\Repository\BaseRepository;
use OurCMS\ShopBundle\Entity\Cart;

class CartRepository extends BaseRepository
{
    /**
     * @return Cart[]
     */
    public function findOld(\DateTimeInterface $threshold): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.updatedAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }
}
