<?php
namespace Galexth\ElasticsearchOdm\Tests\Index;

use Carbon\Carbon;
use Elastica\Client;
use Elastica\Document;
use Faker\Generator;
use Galexth\ElasticsearchOdm\Tests\Models\Company;
use Galexth\ElasticsearchOdm\Tests\Models\Prospect;

class Index
{
    /**
     * @var \Elastica\Client
     */
    private $client;

    /**
     * @var string
     */
    private $index = 'test_12';

    /**
     * @var Generator
     */
    protected $faker;

    /**
     * Index constructor.
     *
     * @param \Elastica\Client|null $client
     */
    public function __construct(Client $client = null)
    {
        if (! $client) {
            $client = new Client();
        }

        $this->client = $client;
        $this->faker = new Generator();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function createIndex()
    {
        $response = $this->client->getIndex($this->index)->create([
            'mappings' => [
                'prospect' => Prospect::mapping(),
                'company' => Company::mapping(),
            ]
        ], true);

        if (! $response->isOk()) {
            throw new \Exception('Index creation failed.');
        }

        $this->indexProspects();
        $this->indexCompanies();

        return true;
    }

    private function indexProspects()
    {
        $documents = [];

        for ($i = 0; $i < 20; $i++) {
            $documents[] = new Document(null, [
                'name' => $this->faker->name(),
                'first_name' => $this->faker->firstName,
                'last_name' => $this->faker->lastName,
                'industry' => null,
                'domain' => $this->faker->domainName,
                'gender' => 'male',
                'status' => 1,
                'stage' => null,
                'companies' => [
                    'name' => $this->faker->company,
                    'revenue' => $this->faker->randomNumber(),
                    'position' => $this->faker->jobTitle,
                    'function' => $this->faker->jobTitle,
                    'seniority_level' => $this->faker->randomElement(['high', 'low', 'medium']),
                ],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        $this->client->getIndex($this->index)->getType('prospect')->addDocuments($documents);
    }

    private function indexCompanies()
    {
        $documents = [];

        for ($i = 0; $i < 20; $i++) {
            $documents[] = new Document(null, [
                'status' => 1,
                'domain' => $this->faker->domainName,
                'industry' => 'It',
                'employees_range' => '1-59 employees',
                'employees_count' => $this->faker->randomNumber(),
                'funding' => $this->faker->randomNumber(),
                'name' => $this->faker->company,
                'revenue' => $this->faker->randomNumber(),
                'position' => $this->faker->jobTitle,
                'function' => $this->faker->jobTitle,
                'seniority_level' => $this->faker->randomElement(['high', 'low', 'medium']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        $this->client->getIndex($this->index)->getType('company')->addDocuments($documents);
    }
}