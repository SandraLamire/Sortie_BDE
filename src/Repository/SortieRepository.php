<?php

namespace App\Repository;

use App\Entity\Etat;
use App\Entity\Sortie;
use App\Model\FiltresAccueil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Sortie>
 *
 * @method Sortie|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sortie|null findOneBy(array $criteria, array $orderBy = null)
 * @method Sortie[]    findAll()
 * @method Sortie[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SortieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sortie::class);
    }

    public function save(Sortie $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Sortie $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByFilters(Etat $etatCreee, Etat $etatHistorisee, FiltresAccueil $filtresAccueil, UserInterface $user, bool $boolean)
    {

        $qb = $this->createQueryBuilder('s');
        $qb->leftJoin('s.etat', 'etat')

            //Ne récupère pas les sorties "Créee" sauf si l'utilisateur est l'organisateur
            ->andWhere('s.etat <> :creee')
            ->orWhere("s.organisateur =  :user")

            //Ne récupère pas les sorties "Historisée"
            ->andWhere('s.etat <> :historisee')
            ->addSelect('etat')
            ->leftJoin('s.organisateur', 'orga')
            ->leftJoin('s.participants', 'part')
            ->leftJoin('s.siteOrganisateur', 'camp')
            ->addSelect('camp')
            ->addSelect('orga')
            ->addSelect('part')
            ->setParameters([
                'creee' => $etatCreee,
                'historisee' => $etatHistorisee,
                'user' => $user,
            ]);

        if ($boolean){

            //Filtre "Sorties sur un campus particulier"
            if ($filtresAccueil->getCampus()) {
                $campus = $filtresAccueil->getCampus();
                $qb->andWhere("s.siteOrganisateur = :campus");
                $qb->setParameter('campus', $campus);
            }

            //Filtre ' Utilisateur organisateur"
            if ($filtresAccueil->isCheckOrga()) {
                $qb->andWhere('s.organisateur = :user');
                $qb->setParameter('user', $user);
            }

            //Filtre "Utilisateur inscrit"
            if ($filtresAccueil->isCheckInscrit()) {
                $qb->andWhere(':user MEMBER OF s.participants');
                $qb->setParameter('user', $user);
            }

            //Filtre "Utilisateur non inscrit"
            if ($filtresAccueil->isCheckNonInscrit()) {
                $qb->andWhere(':user NOT MEMBER OF s.participants');
                $qb->setParameter('user', $user);
            }

            //Filtre "Sorties passées"
            if ($filtresAccueil->isCheckPassees()) {
                $qb->andWhere('s.dateHeureDebut < :date');
                $qb->setParameter('date', new \DateTime());
            }

            //Filtre "Entre telle date"
            if ($filtresAccueil->getDateDebut()) {
                $qb->andWhere('s.dateHeureDebut > :dateFormulaire');
                $qb->setParameter('dateFormulaire', $filtresAccueil->getDateDebut()->format('Y-m-d H:i:s'));
            }

            //Filtre "jusqu'à telle date"
            if ($filtresAccueil->getDateFin()) {
                $qb->andWhere('s.dateHeureDebut < :date');
                $qb->setParameter('date', $filtresAccueil->getDateFin()->format('Y-m-d H:i:s'));
            }

            //Filtre "Rechercher dans le nom de la sortie"
            if ($filtresAccueil->getRecherche()) {
                $qb->andWhere('s.nom LIKE :search');
                $qb->setParameter('search', "%" . $filtresAccueil->getRecherche() . "%");
            }
        }
        return $qb->getQuery()->getResult();
    }


    /**
     * @return Sortie[] Retourne les sorties dont l'état n'est pas "Créee"
     */
    public function findByStatusNotCreated(Etat $etatCreee): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.etat', 'etat')
            ->andWhere('s.etat <> :creee')
            ->setParameter('creee', $etatCreee)
            ->getQuery()
            ->getResult()
        ;
    }

//    public function findOneBySomeField($value): ?Sortie
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
