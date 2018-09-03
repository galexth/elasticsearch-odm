<?php

namespace Galexth\ElasticsearchOdm\Tests;

use Elastica\Query\Ids;
use Illuminate\Support\Collection;
use Galexth\ElasticsearchOdm\OffsetPaginator;
use Galexth\ElasticsearchOdm\Tests\Models\Company;
use Galexth\ElasticsearchOdm\Tests\Models\Prospect;

final class ModelTest extends TestCase
{

    public function testUpdateTimestamps()
    {
        $p = Prospect::first();

        $updatedAt = $p->updated_at;

        $p->touch();

        $this->assertNotEquals($updatedAt, $p->updated_at);

        $updatedAt = $p->updated_at;

        sleep(2);

        $p->update(['first_name' => 'sdfds']);

        $this->assertNotEquals($updatedAt, $p->updated_at);
    }

    public function testCreateTimestamps()
    {
        $p = new Prospect([
            'first_name' => '123wqe',
            'last_name' => 'vxvcv',
        ]);

        $p->save();

        $this->assertNotEmpty($p->created_at, $p->updated_at);
    }

    public function testCreateWithoutTimestamps()
    {
        $p = new Prospect([
            'first_name' => '123wqe',
            'last_name' => 'vxvcv',
        ]);
        $p->created_at = '2018-03-09 16:56:17';
        $p->updated_at = '2018-03-09 16:56:17';

        $p->timestamps = false;

        $p->save();

        $this->assertEquals('2018-03-09 16:56:17', $p->created_at);
        $this->assertEquals('2018-03-09 16:56:17', $p->updated_at);
    }

    public function testAccess()
    {
        $company = Company::query()->first();

        $this->assertTrue($company->isReadOnly());

        $this->expectException('Exception');

        $company->update(['first_name' => 'qqq']);
    }

    public function testSource()
    {
        $collection = Prospect::query()->setSize(10)->get();

        $this->assertNotEmpty($collection, 'Collection is empty');
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertInstanceOf(Prospect::class, $collection->first());
        $this->assertArrayHasKey('last_name', $collection->first()->getAttributes());

        $collection = Prospect::query()->setSize(10)->get(['first_name']);

        $this->assertNotEmpty($collection, 'Collection is empty');
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertInstanceOf(Prospect::class, $collection->first());
        $this->assertArrayNotHasKey('last_name', $collection->first()->getAttributes());

        $collection = Prospect::query()->setSize(10)->withoutSource()->get();

        $this->assertNotEmpty($collection, 'Collection is empty');
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertInstanceOf(Prospect::class, $collection->first());
        $this->assertEmpty($collection->first()->getAttributes());

        $collection = Prospect::query()->setSize(10)->withoutSource()->get();

        $this->assertNotEmpty($collection, 'Collection is empty');
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertInstanceOf(Prospect::class, $collection->first());
        $this->assertEmpty($collection->first()->getAttributes());

        $collection = Prospect::query()->setSize(10)->fields(['first_name'])->get();

        $this->assertNotEmpty($collection, 'Collection is empty');
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertInstanceOf(Prospect::class, $collection->first());
        $this->assertArrayNotHasKey('last_name', $collection->first()->getAttributes());

        $collection = Prospect::query()->setSize(10)->ids();
        $this->assertNotEmpty($collection, 'Collection is empty');
        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function testSerialization()
    {
        $collection = Prospect::query()->setSize(10)->get(['first_name']);

        $this->assertNotEmpty($collection, 'Collection is empty');
        $this->assertInstanceOf(Collection::class, $collection);

        $model = $collection->first();
        $model->setHidden(['id']);

        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertArrayNotHasKey('id', $model->toArray());
    }

    public function testPagination()
    {
        $collection = Prospect::query()->setSize(10)->get();

        $this->assertNotEmpty($collection, 'Collection is empty');
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertInstanceOf(Prospect::class, $collection->first());
        $this->assertCount(10, $collection);

        $fifth = $collection->get(5);

        $collection = Prospect::query()->setSize(10)->setFrom(5)->get();

        $this->assertNotEmpty($collection, 'Collection is empty');
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertInstanceOf(Prospect::class, $collection->first());
        $this->assertCount(10, $collection);
        $this->assertEquals($fifth->first_name, $collection->first()->first_name);

        $paginator = Prospect::query()->paginate(10, 5);
        $collection = $paginator->getCollection();

        $this->assertNotEmpty($collection, 'Collection is empty');
        $this->assertInstanceOf(OffsetPaginator::class, $paginator);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertInstanceOf(Prospect::class, $collection->first());

        $data = $paginator->toArray();

        $this->assertArrayHasKeys(['data', 'limit', 'offset', 'total'], $data);
    }

    public function testSourceSingle()
    {
        $model = Prospect::query()->first();

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertArrayHasKey('last_name', $model->getAttributes());

        $model = Prospect::query()->setSize(10)->first(['first_name']);

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertArrayNotHasKey('last_name', $model->getAttributes());

        $model = Prospect::query()->setSize(10)->withoutSource()->first();

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertEmpty($model->getAttributes());

        $model = Prospect::query()->setSize(10)->withoutSource()->first();

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertEmpty($model->getAttributes());

        $model = Prospect::query()->setSize(10)->fields(['first_name'])->first();

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertArrayNotHasKey('last_name', $model->getAttributes());

        $model = Prospect::query()->setSize(10)->fields([
            'exclude' => ['first_name']
        ])->first();

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertArrayNotHasKey('first_name', $model->getAttributes());
    }

    public function testMagic()
    {
        $model = Prospect::query()->first();

        $this->assertTrue(method_exists($model, 'getFullNameAttribute'));
        $this->assertEquals($model->first_name . ' ' . $model->last_name, $model->full_name);
    }

    public function testRawQuery()
    {
        $q = json_decode('{ "query": { "ids": { "values": [1] } } }', true);

        $model = Prospect::query()->setRawQuery($q)->first();

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertArrayHasKey('last_name', $model->getAttributes());

        $model = Prospect::query()->setRawQuery($q)->setSize(10)->first(['first_name']);

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertArrayNotHasKey('last_name', $model->getAttributes());

        $model = Prospect::query()->setRawQuery($q)->setSize(10)->withoutSource()->first();

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertEmpty($model->getAttributes());

        $model = Prospect::query()->setRawQuery($q)->setSize(10)->withoutSource()->first();

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertEmpty($model->getAttributes());

        $model = Prospect::query()->setRawQuery($q)->setSize(10)->fields(['first_name'])->first();

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertArrayNotHasKey('last_name', $model->getAttributes());

        $model = Prospect::query()->setRawQuery($q)->setSize(10)->fields([
            'exclude' => ['first_name']
        ])->first();

        $this->assertNotEmpty($model, 'Model is empty');
        $this->assertInstanceOf(Prospect::class, $model);
        $this->assertArrayNotHasKey('first_name', $model->getAttributes());
    }

    public function testAppends()
    {
        $collection = Prospect::query()->setSize(10)->get();

        $this->assertNotEmpty($collection, 'Collection is empty');
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertInstanceOf(Prospect::class, $collection->first());

        $first = $collection->first();
        $name = $first->first_name . ' '. $first->last_name;

        $this->assertEquals($name, $first->name);
        $this->assertArrayHasKey('name', $first->toArray());
    }

    public function testScroll()
    {
        $total = 0;
        $collection = Prospect::query()->setSource(['id'])->get();
        $ids = [];

        $query = Prospect::query()->setSize(2)->setSort(['id' => 'asc']);

        foreach ($query->scroll() as $set) {
            $total += $set->count();

            foreach ($set as $item) {
                $ids[] = $item->getId();
            }
        }

        $this->assertEquals($total, $collection->getTotal());
        $this->assertEquals($total, count(array_unique($ids)));
    }

    public function testCasts()
    {
        $prospect = Prospect::query()->first();
        $this->assertInstanceOf(\Galexth\ElasticsearchOdm\Collection::class, $prospect->companies);
    }

    public function testFillable()
    {
        $prospect = Prospect::query()->first();

        $prospect->fill([
            'first_name' => 'test',
            'last_name' => 'last_test',
            'industry' => 'ind_test',
            'emails' => [
                'email' => 'asdsad',
                'is_verified' => false
            ]
        ]);


        $prospect->save(['refresh' => 'true']);

        $prospect = Prospect::query()->find($prospect->getId());

        $this->assertEquals('test', $prospect->first_name);
        $this->assertNotEquals('last_test', $prospect->last_name);
        $this->assertNotEquals('ind_test', $prospect->industry);
    }

    public function testFindOrNew()
    {
        $this->assertNotEmpty($prospect = Prospect::first());
        $this->assertNotEmpty(Prospect::findOrNew($prospect->getId())->getId());
    }

    public function testUpdateOrCreate()
    {
        $this->assertNotEmpty($prospect = Prospect::first());

        $this->assertTrue(Prospect::updateOrCreate($prospect->getId(), ['name' => '123123'], ['refresh' => 'true']));
        $this->assertEquals('123123', Prospect::find($prospect->getId())->name);

        $this->assertTrue(Prospect::updateOrCreate(123123, ['name' => 'test1']));
        $this->assertEquals('test1', Prospect::find(123123)->name);

        $prospect = Prospect::create(['name' => 'test1'], ['refresh' => 'true']);

        $this->assertTrue($prospect->exists);
        $this->assertEquals('test1', Prospect::find($prospect->getId())->name);
    }

    public function testDeleteByQuery()
    {
        $collection = Prospect::query()->setSize(3)->ids();

        $response = Prospect::query()->setQuery(
            new Ids($collection->toArray())
        )->conflicts()->delete();

        $this->assertEquals(3, $response);
    }

    public function testScope()
    {
        $collection = Prospect::query()->allowed()->ids();

        $this->assertCount(1, $collection);
    }

    public function testDefaultValues()
    {
        $prospect = Prospect::query()->find(1);

        $prospect->fill([
            'name' => 'sdfsd',
            'emails' => [
                [
                    'email' => '1232@12323sdf.er',
                    'is_verified' => false,
                    'company_id' => null,
                ],
                [
                    'email' => '1232@cxvxcv.er',
                    'is_verified' => false,
                    'company_id' => null,
                ]
            ],
            'industry' => 'sadsad',
        ]);

        $prospect->save();
//        dd($prospect->emails, $prospect->getId());

    }

}
