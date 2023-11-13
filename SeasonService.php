<?php
namespace App\Service\Season;

use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Season;
use App\Entity\Financial;
use App\Entity\Room;

use DateTime;

/**
 * Description of CleaningService
 *
 * @author olivi
 */
class SeasonService {
    private $season_repository;
    
    public function __construct(private EntityManagerInterface $em) {
        $this->season_repository = $em->getRepository(Season::class);
    }
    
    public function getSeason($date) : array
    {
        $year = $date->format('Y');
        $date->setTime(00, 00, 00);
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-01-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('31-03-'.$year))->format(DateTime::ISO8601)){
            $season = $date->format('Y').' 01 (Hiver)';
            $min = (new \DateTime('01-01-'.$year))->format('d-m-Y');
            $max = (new \DateTime('31-03-'.$year))->format('d-m-Y');
        }
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-04-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('30-06-'.$year))->format(DateTime::ISO8601)){
            $season = $date->format('Y').' 02 (Printemps)';
            $min = (new \DateTime('01-04-'.$year))->format('d-m-Y');
            $max = (new \DateTime('30-06-'.$year))->format('d-m-Y');
        }
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-07-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('30-09-'.$year))->format(DateTime::ISO8601)){
            $season = $date->format('Y').' 03 (Eté)';
            $min = (new \DateTime('01-07-'.$year))->format('d-m-Y');
            $max = (new \DateTime('30-09-'.$year))->format('d-m-Y');
        }
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-10-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('31-12-'.$year))->format(DateTime::ISO8601)){
            $season = $date->format('Y').' 04 (Automne)';
            $min = (new \DateTime('01-10-'.$year))->format('d-m-Y');
            $max = (new \DateTime('31-12-'.$year))->format('d-m-Y');
        }
        
        return array($season, $min, $max);
    }
    
    public function getSeasonName($date) : string
    {
        $year = $date->format('Y');
        $date->setTime(00, 00, 00);
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-01-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('31-03-'.$year))->format(DateTime::ISO8601)){
            return 'hiver';
        }
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-04-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('30-06-'.$year))->format(DateTime::ISO8601)){
            return 'printemps';
        }
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-07-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('30-09-'.$year))->format(DateTime::ISO8601)){
            return 'ete';
        }
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-10-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('31-12-'.$year))->format(DateTime::ISO8601)){
            return 'automne';
        }
    }
    
    public function create($date){
        list($name, $year, $number, $min, $max) = $this->getSeasonInfo($date);
        
        $season = new Season;
        $season->setName($name);
        $season->setYear($year);
        $season->setNumber($number);
        $season->setMinDate($min);
        $season->setMaxDate($max);
        
        $this->season_repository->add($season, true);
        
        return $season;
    }
    
    private function getseasonInfo($date){
        $year = $date->format('Y');
        $date->setTime(00, 00, 00);
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-01-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('31-03-'.$year))->format(DateTime::ISO8601)){
            $name = 'hiver';
            $number = '01';
            $min = (new \DateTime('01-01-'.$year));
            $max = (new \DateTime('31-03-'.$year));
        }
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-04-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('30-06-'.$year))->format(DateTime::ISO8601)){
            $name = 'printemps';
            $number = '02';
            $min = (new \DateTime('01-04-'.$year));
            $max = (new \DateTime('30-06-'.$year));
        }
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-07-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('30-09-'.$year))->format(DateTime::ISO8601)){
            $name = 'ete';
            $number = '03';
            $min = (new \DateTime('01-07-'.$year));
            $max = (new \DateTime('30-09-'.$year));
        }
        
        if($date->format(DateTime::ISO8601) >= (new \DateTime('01-10-'.$year))->format(DateTime::ISO8601) && $date->format(DateTime::ISO8601) <= (new \DateTime('31-12-'.$year))->format(DateTime::ISO8601)){
            $name = 'automne';
            $number = '04';
            $min = (new \DateTime('01-10-'.$year));
            $max = (new \DateTime('31-12-'.$year));
        }
        
        return array($name, $year, $number, $min, $max);
    }
}
