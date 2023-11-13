<?php
namespace App\Service\Booking;

use Doctrine\ORM\EntityManagerInterface;
use App\Service\Housekeeping\HousekeepingManager;
use App\Service\Booking\EmailService;
use App\Service\Season\SeasonService;

use App\Entity\Room;
use App\Entity\Guest;
use App\Entity\Amount;
use App\Entity\Booking;
use App\Entity\Season;
use App\Entity\Hotel;
use App\Entity\Referer;
use App\Entity\Housekeeping;
use App\Entity\Housekeeper;
use App\Entity\User;
use App\Entity\Location;
use App\Entity\Financial;

/**
 * Description of CleaningService
 * 
 * @author olivi
 */
class NewService {
    private $financial_repository;
    
    public function __construct(
            private EntityManagerInterface $em, 
            private SeasonService $season_service, 
            private HousekeepingManager $housekeepingManager, 
            private EmailService $email_service,
    ) {
        $this->financial_repository = $em->getRepository(Financial::class);
    }
    
    public function manage($beds24_booking){
        $booking = $this->getBookingEntity($beds24_booking);
        
        if($booking->getStatus() != 'canceled'){
            
            $now = new \Datetime;
            if($now > $booking->getHousekeeping()->getSendingDate() && $now <= $booking->getDeparture()){
                $this->housekeepingManager->manageBookingChanges($booking, 'new');
            }
            
            $this->email_service->send('Nouvelle Réservation', 'new-booking', $booking);
        }
        
        return true;
    }
    
    private function getBookingEntity($beds24_booking): Booking
    {
        $booking = new Booking();
        $booking->completeEntity($beds24_booking);
        
        try{
            $room = $this->em->getRepository(Room::class)->findOneBy(['beds_id' => $beds24_booking['roomId']]);
        } catch (Exception $ex) {
            throw $ex->getMessage();
        }
        
        $hotel = $room->getHotel();
        
        $season = $this->em->getRepository(Season::class)->findBetweenTwoDates($booking->getArrival());
        if(!$season){
            $season = $this->season_service->create($booking->getArrival());
        }
        
        $this->manageFinancial($season, $room);
        
        $guest = $this->findOrCreateGuest($beds24_booking);
        if($guest->getId() == 358){
            $booking->setStatus('blocked');}
        
        $referer = $this->getReferer($beds24_booking, $hotel);
        $booking->setGuest($guest);
        $booking->setReferer($referer);   
           
        $booking->setSeason($season);
        $booking->setRoom($room);
        $booking->setHotel($hotel);

        $amount = new Amount();
        $amount->manage($beds24_booking, $booking);
        $booking->setAmount($amount);
        
        $booking->setHousekeeping($this->getHousekeeping($room, clone($booking->getDeparture())));

        $this->em->persist($booking);
        $this->em->flush();

        return $booking;
    }
    
    private function findOrCreateGuest($imported_boooking): Guest
    {
        $em = $this->em;
        
        if($imported_boooking['guestFirstName'] == 'Bloqué'){
            $guest = $em->find(Guest::class, 358);
        }else{
            var_dump($imported_boooking['guestFirstName']." ".$imported_boooking['guestName']);
            $guest = $em->getRepository(Guest::class)->findByFirstnameAndName($imported_boooking['guestFirstName'], $imported_boooking['guestName']);
            if(!$guest){
                $location = new Location;
                $location->manageData($imported_boooking);
                $guest = new Guest();
                $guest->manageImportedGuest($imported_boooking, $location);
            }
        }
        
        return $guest;
    }
    
    private function getReferer($imported_booking, Hotel $hotel): Referer
    {
        $referer_list = $hotel->getReferers();
        foreach($referer_list as $referer){
            if(str_contains($imported_booking['referer'], $referer->getKeyword())){
                return $referer;
            }
        }
        
        return $this->em->getRepository(Referer::class)->find(10);
    }
    
    private function getHousekeeping(Room $room, $departure): Housekeeping
    {
        $housekeeping = new Housekeeping;
        $cleaners = $this->em->getRepository(User::class)->findByRoomAndRole($room->getId(), 'ROLE_CLEANING');
        
        $position = 0;
        foreach($cleaners as $cleaner){
            $position++;
            $housekeeper = new Housekeeper($cleaner, $position);
            if($position == 1){$housekeeper->setStatus('in_progress');}
            $housekeeping->addHousekeeper($housekeeper);
        }
        
        $housekeeping->setSendingDate($departure->modify('-7 days'));
        $status = $position == 0 ? 'no_cleaner' : 'pending';
        $housekeeping->setStatus($status);
        
        return $housekeeping;
    }
    
    private function manageFinancial($season, $room){
        $financial = $this->financial_repository->findOneBy(['season' => $season, 'room' => $room]);
        if(!$financial){
            $financial = new Financial;
            $financial->setRoom($room);
            $financial->setSeason($season);
            
            $this->financial_repository->add($financial, true);
        }
    }
}
