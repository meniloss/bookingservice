<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use App\Service\Housekeeping\HousekeepingManager;
use App\Service\Booking\BookingService;

use App\Entity\Hotel;
use App\Entity\Housekeeper;
use App\Entity\TemporaryBooking;

class WebhookController extends AbstractController
{
    private $temporary_booking_repository;
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HousekeepingManager $housekeepingManager,
    ) {
        $this->temporary_booking_repository = $entityManager->getRepository(TemporaryBooking::class);
    }
    
    public function booking(Request $request, Hotel $hotel, BookingService $booking_service) : Response 
    {
        $book_id = $request->get('bookid');
        
        $temporary_booking =$this->temporary_booking_repository->findOneBy(['book_id' => $book_id]);
        if(!$temporary_booking){
            $temporary_booking = new TemporaryBooking($book_id, $hotel);
            
            $this->temporary_booking_repository->add($temporary_booking, true);
        }     
        
        $booking_service->manageBookId($hotel, $book_id);
        
        $this->temporary_booking_repository->remove($temporary_booking, true);
        
        return new Response('ok'); 
    }
}

<?php
namespace App\Service\Booking;

use App\Service\Beds24\Beds24Service;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Season\SeasonService;

use App\Service\Booking\ModificationService;
use App\Service\Booking\CancelService;
use App\Service\Booking\NewService;

use App\Entity\Room;
use App\Entity\Booking;
use App\Entity\Hotel;

/**
 * Description of CleaningService
 * 
 * @author olivi
 */
class BookingService {
    private $beds_24_service;
    
    private $season_service;
    
    private $em;
    
    private $modification_service;
    
    private $cancel_service;
    
    private $new_service;
    
    private $booking_repository;
    
    public function __construct(Beds24Service $beds24_service, EntityManagerInterface $em, CancelService $cancel_service, NewService $new_service, ModificationService $modification_service, SeasonService $season_service, \Twig\Environment $templating) {
        $this->beds_24_service = $beds24_service;
        $this->season_service = $season_service;
        $this->em = $em;
        $this->booking_repository = $em->getRepository(Booking::class);
        $this->modification_service = $modification_service;
        $this->cancel_service = $cancel_service;
        $this->new_service = $new_service;
    }
    
    public function createBooking(Room $room, $arrival, $departure){
        $first_night = new \DateTime($arrival);
        $last_night = new \DateTime($departure);
        
        $price = $this->getPrice($room, $first_night, $last_night);
        
        if($price){
            $final_amount = $this->getFinalAmount($price, $first_night, $room->getHotel()); 
            
            $data = [
                "roomId"            => $room->getBedsId(),
                "unitId"            => "1",
                "roomQty"           => "1",
                "status"            => "1",
                "firstNight"        => $first_night->format('d-m-Y'),
                "lastNight"         => $last_night->modify('-1 day')->format('d-m-Y'),
                "numAdult"          => "1",
                "guestFirstName"    => "Bloqué",
                "checkAvailability" => true,
                "price"             => $final_amount,
                "invoice"           => [
                                            array(
                                                "description"   => "lodging",
                                                "status"        => "",
                                                "qty"           => "1",
                                                "price"         => $final_amount,
                                                "vatRate"       => "6",
                                                "invoiceeId"    => ""
                                            )
                                        ],
                "infoItems" =>      [
                                        array(
                                            "code"  => "BLOCKBYCLIENT",
                                            "text"  => "Bloqué par le client via le site"
                                        )
                                    ],
            ];

            $this->beds_24_service->request('POST', $room->getHotel()->getBeds24Setting()->getPropKey(), 'setBooking', $data);
            
            return true;
        }else{
            return false;
        } 
    }
    
    public function getListOfBooking(Hotel $hotel){
        $data = [
             "arrivalFrom" => "20220701",
        ];
        
        $result = $this->beds_24_service->request('POST', $hotel->getBeds24Setting()->getPropKey(), 'getBookings', $data);
        
        return $result;
    }
    
    public function modifyStatus(Booking $booking, $status){
        if($status == 'canceled'){
            $data = [
                "bookId"    => $booking->getReference(),
                "status"    => "0",
            ];
        }else{
            $data = [
                "bookId"    => $booking->getReference(),
                "status"    => "1",
            ];
        }
        
        $this->beds_24_service->request('POST', $booking->getHotel()->getSetting()->getPropKey(), 'setBooking', $data);
    }
    
    public function manageBookId(Hotel $hotel, $book_id, $beds24_booking = null){
        
        if($beds24_booking == null){
            if(!$beds24_booking = $this->getBookingFromBeds24($hotel->getBeds24Setting()->getPropKey(), $book_id)){
                return false;}
        }
        
        list($type, $booking) = $this->manageBeds24Booking($beds24_booking);
        
        switch($type){
            case 'annulation':
                $this->cancel_service->manage($booking, $beds24_booking);
                break;
            case 'modification':
                $this->modification_service->manage($booking, $beds24_booking);
                break;
            case 'nouvelle':
                $this->new_service->manage($beds24_booking);
                break;                
        }
        
        return true;
    } 
    
    private function getPrice(Room $room, $first_night, $last_night){
        
        $data = [
            "checkIn"   => $first_night->format('Ymd'),
            "checkOut"  => $last_night->format('Ymd'),
            "numAdult"  => "2",
            "numChild"  => "0",
            "propId"    => $room->getHotel()->getBeds24Setting()->getPropId(),
            "roomId"    => $room->getBedsId()
        ];
        
        $result = $this->beds_24_service->request('GET', $room->getHotel()->getBeds24Setting()->getPropKey(), 'getAvailabilities', $data);
        if($result[$room->getBedsId()]['roomsavail'] == 0){
            return false;
        }else{
            return $result[$room->getBedsId()]['price'];
        }
    }
    
    private function getFinalAmount($price, $first_night, Hotel $hotel){
        $season_service = $this->season_service;
        $season = $season_service->getSeasonName($first_night);
        
        $percentage = $hotel->getHotelCharge()->getZone()->get($season);
        
        return $price*$percentage/100;
    }
    
    public function getBookingFromBeds24($prop_key, $book_id){        
        if($prop_key == null){
            return false;
        }
        
        $data['bookId'] = $book_id;
        $data["includeInvoice"] =  true;
        
        $imported_bookings = $this->beds_24_service->request('GET', $prop_key, 'getBookings', $data);
        //var_dump($prop_key);
        //var_dump($imported_bookings);
        foreach ($imported_bookings as $imported_booking){
            if($imported_booking['apiReference'] != '' || $imported_booking['bookId'] != ''){
               return $imported_booking;
            }
        }
        
        return false;
    }
    
    private function manageBeds24Booking($beds24_booking){        
        $booking = $this->booking_repository->findOneByBookId($beds24_booking['bookId']);  
        if($booking){
            if($beds24_booking['status'] == 0){
                return ['annulation', $booking];
            }else{
                return ['modification', $booking];
            }
        }else{
            return ['nouvelle', null];
        }
    }
}

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

<?php
namespace App\Service\Booking;

use Doctrine\ORM\EntityManagerInterface;

use App\Service\Housekeeping\HousekeepingManager;
use App\Service\Booking\CancelService;
use App\Service\Booking\NewService;
use App\Service\Booking\EmailService;

use App\Entity\Booking;

/**
 * Description of CleaningService
 * 
 * @author olivi
 */
class ModificationService {
    
    public function __construct(
            private EntityManagerInterface $em, 
            private HousekeepingManager $housekeepingManager, 
            private CancelService $cancel_service, 
            private NewService $new_service, 
            private EmailService $email_service,
    ) {}
    
    public function manage(Booking $booking, $beds24_booking){
        $modification = false;
        $old_booking = clone $booking;
        
        if($booking->getAmount()->getPrice() != $beds24_booking['price']){
            $this->managePrice($booking, $beds24_booking);
        }
        
        if($booking->getArrival() != new \DateTime($beds24_booking['firstNight']) && $booking->getDeparture() != (new \DateTime($beds24_booking['lastNight']))->modify('+1 day')){
            $modification = $this->manageArrivalDeparture($booking, $beds24_booking);
        }else{
            if($booking->getArrival() != new \DateTime($beds24_booking['firstNight'])){
                $modification = $this->manageArrival($booking, $beds24_booking);
            }

            if($booking->getDeparture() != (new \DateTime($beds24_booking['lastNight']))->modify('+1 day')){
                $modification = $this->manageDeparture($booking, $beds24_booking);
            }
        } 
        
        if($booking->getRoom()->getBedsId() != $beds24_booking['roomId']){
            $this->manageRoom($booking, $beds24_booking);
        }
        
        if($modification){
            $this->email_service->send('Modification de réservation', 'new-modification', $booking, $old_booking, $modification);
        }
        
        return true;
    }
    
    private function managePrice(Booking $booking, $beds24_booking){
        $amount = $booking->getAmount();
        $amount->manage($beds24_booking, $booking);

        $this->em->persist($amount); 
        $this->em->flush();
        
        return true;
    }
    
    private function manageArrivalDeparture(Booking $booking, $beds24_booking){
        $arrival = new \DateTime($beds24_booking['firstNight']);
        $departure = (new \DateTime($beds24_booking['lastNight']))->modify('+1 day'); 
        $sending_date = (new \DateTime($beds24_booking['lastNight']))->modify('-6 days');
        $old_date = clone $booking->getDeparture();
        $old_sending_date = clone $booking->getHousekeeping()->getSendingDate();
        
        $booking->setArrival($arrival);
        $booking->setDeparture($departure);
        $booking->getHousekeeping()->setSendingDate($sending_date);

        $this->em->persist($booking);
        $this->em->flush();
        
        $now = (new \Datetime)->setTime(00, 00, 00);
        if($now > $old_sending_date && $now <= $old_date){
            $this->housekeepingManager->manageBookingChanges($booking, 'modification', $old_date);
        }
        
        return 'arrival-departure';
    }
    
    private function manageArrival(Booking $booking, $beds24_booking){
        $arrival = new \DateTime($beds24_booking['firstNight']);
        $booking->setArrival($arrival);

        $this->em->persist($booking);
        $this->em->flush();
        
        return 'arrival';
    }
    
    private function manageDeparture(Booking $booking, $beds24_booking){
        $departure = (new \DateTime($beds24_booking['lastNight']))->modify('+1 day'); 
        $sending_date = (new \DateTime($beds24_booking['lastNight']))->modify('-6 days');
        $old_date = clone $booking->getDeparture();
        $old_sending_date = clone $booking->getHousekeeping()->getSendingDate();
        
        
        $booking->setDeparture($departure);
        $booking->getHousekeeping()->setSendingDate($sending_date);

        $this->em->persist($booking);
        $this->em->flush();

        $now = (new \Datetime)->setTime(00, 00, 00);
        if($now > $old_sending_date && $now <= $old_date){
            $this->housekeepingManager->manageBookingChanges($booking, 'modification', $old_date);
        }
        
        return  'departure';
    }
    
    private function manageRoom(Booking $booking, $beds24_booking){
        $this->cancel_service->manage($booking);
        $this->new_service->manage($beds24_booking);
        
        return true;
    }
}

<?php
namespace App\Service\Booking;

use Doctrine\ORM\EntityManagerInterface;
use App\Service\Housekeeping\HousekeepingManager;
use App\Service\Booking\EmailService;

use App\Entity\Booking;

/**
 * Description of CleaningService
 * 
 * @author olivi
 */
class CancelService {
    
    public function __construct(
            private EntityManagerInterface $em, 
            private HousekeepingManager $housekeepingManager, 
            private EmailService $email_service) {}
    
    public function manage(Booking $booking, $beds24_booking){
        $booking->setStatus("canceled");
        if($booking->getAmount()->getPrice() != $beds24_booking['price']){
            $this->managePrice($booking, $beds24_booking);
        }
        
        $this->em->persist($booking);
        $this->em->flush();
        
        $now = new \Datetime;
        if($now > $booking->getHousekeeping()->getSendingDate() && $now <= $booking->getDeparture()){
            $this->housekeepingManager->manageBookingChanges($booking, 'canceled');
        }
        
        $this->email_service->send('Annulation de réservation', 'reservation-canceled', $booking);
        
        return true;
    }
    
    private function managePrice(Booking $booking, $beds24_booking){
        $amount = $booking->getAmount();
        $amount->manage($beds24_booking, $booking);

        $this->em->persist($amount); 
        $this->em->flush();
        
        return true;
    }
}

<?php
namespace App\Service\Booking;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Twig\Environment;
use Symfony\Bridge\Twig\Mime\BodyRenderer;

use App\Entity\Booking;
use App\Entity\User;

/**
 * Description of CleaningService
 * 
 * @author olivi
 */
class EmailService {    
    public function __construct(
            private EntityManagerInterface $em, 
            private MailerInterface $mailer, 
            private Environment $twig
    ) {}
    
    public function send($subject, $template, Booking $booking, $old_booking = null, $type = null){        
        $hotel = $booking->getHotel();
        
        $to = [];
        $users = $this->em->getRepository(User::class)->findByHotelAndRole($hotel->getId(), 'ROLE_HOTEL'); 
        foreach($users as $user){$to[] = $user->getEmail();}
        
        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@escappart.com', "Esc'Appart No-Reply"))
            ->to(...$to)
            ->cc('info@escappart.com')
            ->subject("Esc'APPART : ".$subject)
            ->htmlTemplate('email/booking/'.$template.'.html.twig')
            ->textTemplate('email/booking/'.$template.'.txt.twig')
            ->context([
                'stay' => $booking,
                'oldstay' => $old_booking,
                'hotel' => $hotel,
                'type' => $type
            ]);
        
        $renderer = new BodyRenderer($this->twig);
        $renderer->render($email);

        try{
            $this->mailer->send($email); 
        } catch (Exception $ex) {
            throw $ex->getMessage();
        }
    }
}
