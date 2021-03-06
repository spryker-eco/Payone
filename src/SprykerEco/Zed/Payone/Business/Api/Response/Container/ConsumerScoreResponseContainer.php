<?php

/**
 * MIT License
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerEco\Zed\Payone\Business\Api\Response\Container;

class ConsumerScoreResponseContainer extends AbstractResponseContainer
{
    /**
     * @var int
     */
    protected $secstatus;

    /**
     * @var string
     */
    protected $score;

    /**
     * @var int
     */
    protected $scorevalue;

    /**
     * @var string
     */
    protected $secscore;

    /**
     * @var string
     */
    protected $divergence;

    /**
     * @var string
     */
    protected $personstatus;

    /**
     * @var string
     */
    protected $firstname;

    /**
     * @var string
     */
    protected $lastname;

    /**
     * @var string
     */
    protected $street;

    /**
     * @var string
     */
    protected $streetname;

    /**
     * @var string
     */
    protected $streetnumber;

    /**
     * @var string
     */
    protected $zip;

    /**
     * @var string
     */
    protected $city;

    /**
     * @var string
     */
    protected $gender;

    /**
     * @param string $city
     *
     * @return void
     */
    public function setCity($city)
    {
        $this->city = $city;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param string $divergence
     *
     * @return void
     */
    public function setDivergence($divergence)
    {
        $this->divergence = $divergence;
    }

    /**
     * @return string
     */
    public function getDivergence()
    {
        return $this->divergence;
    }

    /**
     * @param string $firstname
     *
     * @return void
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;
    }

    /**
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * @param string $lastname
     *
     * @return void
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;
    }

    /**
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * @param string $personstatus
     *
     * @return void
     */
    public function setPersonstatus($personstatus)
    {
        $this->personstatus = $personstatus;
    }

    /**
     * @return string
     */
    public function getPersonstatus()
    {
        return $this->personstatus;
    }

    /**
     * @param string $score
     *
     * @return void
     */
    public function setScore($score)
    {
        $this->score = $score;
    }

    /**
     * @return string
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * @param int $scorevalue
     *
     * @return void
     */
    public function setScorevalue($scorevalue)
    {
        $this->scorevalue = $scorevalue;
    }

    /**
     * @return int
     */
    public function getScorevalue()
    {
        return $this->scorevalue;
    }

    /**
     * @param string $secscore
     *
     * @return void
     */
    public function setSecscore($secscore)
    {
        $this->secscore = $secscore;
    }

    /**
     * @return string
     */
    public function getSecscore()
    {
        return $this->secscore;
    }

    /**
     * @param int $secstatus
     *
     * @return void
     */
    public function setSecstatus($secstatus)
    {
        $this->secstatus = $secstatus;
    }

    /**
     * @return int
     */
    public function getSecstatus()
    {
        return $this->secstatus;
    }

    /**
     * @param string $street
     *
     * @return void
     */
    public function setStreet($street)
    {
        $this->street = $street;
    }

    /**
     * @return string
     */
    public function getStreet()
    {
        return $this->street;
    }

    /**
     * @param string $streetname
     *
     * @return void
     */
    public function setStreetname($streetname)
    {
        $this->streetname = $streetname;
    }

    /**
     * @return string
     */
    public function getStreetname()
    {
        return $this->streetname;
    }

    /**
     * @param string $streetnumber
     *
     * @return void
     */
    public function setStreetnumber($streetnumber)
    {
        $this->streetnumber = $streetnumber;
    }

    /**
     * @return string
     */
    public function getStreetnumber()
    {
        return $this->streetnumber;
    }

    /**
     * @param string $zip
     *
     * @return void
     */
    public function setZip($zip)
    {
        $this->zip = $zip;
    }

    /**
     * @return string
     */
    public function getZip()
    {
        return $this->zip;
    }

    /**
     * @return string|null
     */
    public function getGender(): ?string
    {
        return $this->gender;
    }

    /**
     * @param string $gender
     *
     * @return void
     */
    public function setGender(string $gender): void
    {
        $this->gender = $gender;
    }
}
