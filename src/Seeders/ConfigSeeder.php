<?php


namespace Crm\SubscriptionsModule\Seeders;

use Crm\ApplicationModule\Builder\ConfigBuilder;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Config\Repository\ConfigCategoriesRepository;
use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ConfigsTrait;
use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SubscriptionsModule\Models\Config;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigSeeder implements ISeeder
{
    use ConfigsTrait;

    private $configCategoriesRepository;

    private $configsRepository;

    private $configBuilder;

    public function __construct(
        ConfigCategoriesRepository $configCategoriesRepository,
        ConfigsRepository $configsRepository,
        ConfigBuilder $configBuilder
    ) {
        $this->configCategoriesRepository = $configCategoriesRepository;
        $this->configsRepository = $configsRepository;
        $this->configBuilder = $configBuilder;
    }

    public function seed(OutputInterface $output)
    {
        $category = $this->getCategory($output, 'subscriptions.config.category', 'fa fa-tag', 300);

        $this->addConfig(
            $output,
            $category,
            'vat_default',
            ApplicationConfig::TYPE_STRING,
            'subscriptions.config.vat_default.name',
            'subscriptions.config.vat_default.description',
            '20',
            120
        );

        $category = $this->getCategory($output, 'subscriptions.config.users.category', 'fa fa-user', 300);

        $this->addConfig(
            $output,
            $category,
            Config::BLOCK_ANONYMIZATION,
            ApplicationConfig::TYPE_BOOLEAN,
            'subscriptions.config.users.prevent_anonymization.name',
            'subscriptions.config.users.prevent_anonymization.description',
            true,
            120
        );

        $this->addConfig(
            $output,
            $category,
            Config::BLOCK_ANONYMIZATION_WITHIN_DAYS,
            ApplicationConfig::TYPE_INT,
            'subscriptions.config.users.prevent_anonymization_within_days.name',
            'subscriptions.config.users.prevent_anonymization_within_days.description',
            90,
            120
        );
    }
}
