<?php

namespace App\Entity;

use App\Repository\SeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: '`escappart_season`')]
#[ORM\Entity(repositoryClass: SeasonRepository::class)]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $year = null;

    #[ORM\Column(length: 255)]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $min_date = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $max_date = null;

    #[ORM\OneToMany(mappedBy: 'season', targetEntity: Booking::class, orphanRemoval: true)]
    private Collection $bookings;

    #[ORM\OneToMany(mappedBy: 'season', targetEntity: Financial::class)]
    private Collection $financials;

    #[ORM\OneToMany(mappedBy: 'season', targetEntity: Accounting::class, orphanRemoval: true)]
    private Collection $accountings;

    #[ORM\Column]
    private ?bool $billed = null;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
        $this->financials = new ArrayCollection();
        $this->accountings = new ArrayCollection();
    }

    public function __toString() {
        return $this->year.'-'.$this->number.' ('.$this->name.')';
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getMinDate(): ?\DateTimeInterface
    {
        return $this->min_date;
    }

    public function setMinDate(\DateTimeInterface $min_date): self
    {
        $this->min_date = $min_date;

        return $this;
    }

    public function getMaxDate(): ?\DateTimeInterface
    {
        return $this->max_date;
    }

    public function setMaxDate(\DateTimeInterface $max_date): self
    {
        $this->max_date = $max_date;

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setSeason($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getSeason() === $this) {
                $booking->setSeason(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Financial>
     */
    public function getFinancials(): Collection
    {
        return $this->financials;
    }

    public function addFinancial(Financial $financial): self
    {
        if (!$this->financials->contains($financial)) {
            $this->financials->add($financial);
            $financial->setSeason($this);
        }

        return $this;
    }

    public function removeFinancial(Financial $financial): self
    {
        if ($this->financials->removeElement($financial)) {
            // set the owning side to null (unless already changed)
            if ($financial->getSeason() === $this) {
                $financial->setSeason(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Accounting>
     */
    public function getAccountings(): Collection
    {
        return $this->accountings;
    }

    public function addAccounting(Accounting $accounting): self
    {
        if (!$this->accountings->contains($accounting)) {
            $this->accountings->add($accounting);
            $accounting->setSeason($this);
        }

        return $this;
    }

    public function removeAccounting(Accounting $accounting): self
    {
        if ($this->accountings->removeElement($accounting)) {
            // set the owning side to null (unless already changed)
            if ($accounting->getSeason() === $this) {
                $accounting->setSeason(null);
            }
        }

        return $this;
    }

    public function isBilled(): ?bool
    {
        return $this->billed;
    }

    public function setBilled(bool $billed): self
    {
        $this->billed = $billed;

        return $this;
    }
}
