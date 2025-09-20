<?php

namespace OurCMS\ShopBundle\Repository;

use OurCMS\BaseBundle\Repository\BaseRepository;

class CartRepository extends BaseRepository
{
    /**
     * @return \OurCMS\ShopBundle\Entity\Cart[]
     */
    public function findCartsOlderThan(\DateTime $dateTime): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.updatedAt < :date')
            ->setParameter('date', $dateTime)
            ->getQuery()
            ->getResult();
    }
}