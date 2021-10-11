<?php

namespace Crm\SubscriptionsModule\Report;

use Nette\Database\Explorer;
use Nette\Localization\ITranslator;

abstract class BaseReport implements ReportInterface
{
    private $name;

    private $id;

    /** @var Explorer */
    private $db;

    protected $translator;

    public function __construct($name, ITranslator $translator)
    {
        $this->name = $name;
        $this->id = md5(time() . rand(1, 10000) . rand(1000, 1000) . 'hello');
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
