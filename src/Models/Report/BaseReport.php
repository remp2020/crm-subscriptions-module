<?php

namespace Crm\SubscriptionsModule\Models\Report;

use Nette\Database\Explorer;
use Nette\Localization\Translator;
use Nette\Utils\Random;

abstract class BaseReport implements ReportInterface
{
    private $name;

    private $id;

    /** @var Explorer */
    private $db;

    protected $translator;

    public function __construct($name, Translator $translator)
    {
        $this->name = $name;
        $this->id = Random::generate(16);
        $this->translator = $translator;
    }

    protected function getDatabase()
    {
        return $this->db;
    }

    public function getName()
    {
        return $this->name;
    }

    public function injectDatabase(Explorer $db)
    {
        $this->db = $db;
    }

    public function getId()
    {
        return $this->id;
    }
}
