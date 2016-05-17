<?php

namespace Doctrine\Tests\ORM\Cache\Persister\Collection;

use Doctrine\Tests\OrmTestCase;

use Doctrine\ORM\Cache\Region;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Persisters\Collection\CollectionPersister;

use Doctrine\Tests\Models\Cache\State;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @group DDC-2183
 */
abstract class AbstractCollectionPersisterTest extends OrmTestCase
{
    /**
     * @var \Doctrine\ORM\Cache\Region
     */
    protected $region;

    /**
     * @var \Doctrine\ORM\Persisters\Collection\CollectionPersister
     */
    protected $collectionPersister;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var array
     */
    protected $regionMockMethods = array(
        'getName',
        'contains',
        'get',
        'getMultiple',
        'put',
        'evict',
        'evictAll'
    );

    /**
     * @var array
     */
    protected $collectionPersisterMockMethods = array(
        'delete',
        'update',
        'count',
        'slice',
        'contains',
        'containsKey',
        'removeElement',
        'removeKey',
        'get',
        'getMultiple',
        'loadCriteria'
    );

    /**
     * @param \Doctrine\ORM\EntityManager                             $em
     * @param \Doctrine\ORM\Persisters\Collection\CollectionPersister $persister
     * @param \Doctrine\ORM\Cache\Region                              $region
     * @param array                                                   $mapping
     *
     * @return \Doctrine\ORM\Cache\Persister\Collection\AbstractCollectionPersister
     */
    abstract protected function createPersister(EntityManager $em, CollectionPersister $persister, Region $region, array $mapping);

    protected function setUp()
    {
        $this->getSharedSecondLevelCacheDriverImpl()->flushAll();
        $this->enableSecondLevelCache();
        parent::setUp();

        $this->em                   = $this->_getTestEntityManager();
        $this->region               = $this->createRegion();
        $this->collectionPersister  = $this->getMock(
            'Doctrine\ORM\Persisters\Collection\CollectionPersister',
            $this->collectionPersisterMockMethods
        );
    }

    /**
     * @return \Doctrine\ORM\Cache\Region
     */
    protected function createRegion()
    {
        return $this->getMock('Doctrine\ORM\Cache\Region', $this->regionMockMethods);
    }

    /**
     * @return \Doctrine\ORM\PersistentCollection
     */
    protected function createCollection($owner, $assoc = null, $class = null, $elements = null)
    {
        $em    = $this->em;
        $class = $class ?: $this->em->getClassMetadata('Doctrine\Tests\Models\Cache\State');
        $assoc = $assoc ?: $class->associationMappings['cities'];
        $coll  = new \Doctrine\ORM\PersistentCollection($em, $class, $elements ?: new ArrayCollection);

        $coll->setOwner($owner, $assoc);
        $coll->setInitialized(true);

        return $coll;
    }

    protected function createPersisterDefault()
    {
        $assoc = $this->em->getClassMetadata('Doctrine\Tests\Models\Cache\State')->associationMappings['cities'];

        return $this->createPersister($this->em, $this->collectionPersister, $this->region, $assoc);
    }

    public function testImplementsEntityPersister()
    {
        $persister = $this->createPersisterDefault();

        self::assertInstanceOf('Doctrine\ORM\Persisters\Collection\CollectionPersister', $persister);
        self::assertInstanceOf('Doctrine\ORM\Cache\Persister\CachedPersister', $persister);
        self::assertInstanceOf('Doctrine\ORM\Cache\Persister\Collection\CachedCollectionPersister', $persister);
    }

    public function testInvokeDelete()
    {
        $entity     = new State("Foo");
        $persister  = $this->createPersisterDefault();
        $collection = $this->createCollection($entity);

        $this->em->getUnitOfWork()->registerManaged($entity, array('id'=>1), array('id'=>1, 'name'=>'Foo'));

        $this->collectionPersister->expects($this->once())
            ->method('delete')
            ->with($this->equalTo($collection));

        self::assertNull($persister->delete($collection));
    }

    public function testInvokeUpdate()
    {
        $entity     = new State("Foo");
        $persister  = $this->createPersisterDefault();
        $collection = $this->createCollection($entity);

        $collection->setDirty(true);

        $this->em->getUnitOfWork()->registerManaged($entity, array('id'=>1), array('id'=>1, 'name'=>'Foo'));

        $this->collectionPersister->expects($this->once())
            ->method('update')
            ->with($this->equalTo($collection));

        self::assertNull($persister->update($collection));
    }

    public function testInvokeCount()
    {
        $entity     = new State("Foo");
        $persister  = $this->createPersisterDefault();
        $collection = $this->createCollection($entity);

        $this->em->getUnitOfWork()->registerManaged($entity, array('id'=>1), array('id'=>1, 'name'=>'Foo'));

        $this->collectionPersister->expects($this->once())
            ->method('count')
            ->with($this->equalTo($collection))
            ->will($this->returnValue(0));

        self::assertEquals(0, $persister->count($collection));
    }

    public function testInvokeSlice()
    {
        $entity     = new State("Foo");
        $persister  = $this->createPersisterDefault();
        $collection = $this->createCollection($entity);
        $slice      = $this->createCollection($entity);

        $this->em->getUnitOfWork()->registerManaged($entity, array('id'=>1), array('id'=>1, 'name'=>'Foo'));

        $this->collectionPersister->expects($this->once())
            ->method('slice')
            ->with($this->equalTo($collection), $this->equalTo(1), $this->equalTo(2))
            ->will($this->returnValue($slice));

        self::assertEquals($slice, $persister->slice($collection, 1 , 2));
    }

    public function testInvokeContains()
    {
        $entity     = new State("Foo");
        $element    = new State("Bar");
        $persister  = $this->createPersisterDefault();
        $collection = $this->createCollection($entity);

        $this->em->getUnitOfWork()->registerManaged($entity, array('id'=>1), array('id'=>1, 'name'=>'Foo'));

        $this->collectionPersister->expects($this->once())
            ->method('contains')
            ->with($this->equalTo($collection), $this->equalTo($element))
            ->will($this->returnValue(false));

        self::assertFalse($persister->contains($collection,$element));
    }

    public function testInvokeContainsKey()
    {
        $entity     = new State("Foo");
        $persister  = $this->createPersisterDefault();
        $collection = $this->createCollection($entity);

        $this->em->getUnitOfWork()->registerManaged($entity, array('id'=>1), array('id'=>1, 'name'=>'Foo'));

        $this->collectionPersister->expects($this->once())
            ->method('containsKey')
            ->with($this->equalTo($collection), $this->equalTo(0))
            ->will($this->returnValue(false));

        self::assertFalse($persister->containsKey($collection, 0));
    }

    public function testInvokeRemoveElement()
    {
        $entity     = new State("Foo");
        $element    = new State("Bar");
        $persister  = $this->createPersisterDefault();
        $collection = $this->createCollection($entity);

        $this->em->getUnitOfWork()->registerManaged($entity, array('id'=>1), array('id'=>1, 'name'=>'Foo'));

        $this->collectionPersister->expects($this->once())
            ->method('removeElement')
            ->with($this->equalTo($collection), $this->equalTo($element))
            ->will($this->returnValue(false));

        self::assertFalse($persister->removeElement($collection, $element));
    }

    public function testInvokeGet()
    {
        $entity     = new State("Foo");
        $element    = new State("Bar");
        $persister  = $this->createPersisterDefault();
        $collection = $this->createCollection($entity);

        $this->em->getUnitOfWork()->registerManaged($entity, array('id'=>1), array('id'=>1, 'name'=>'Foo'));

        $this->collectionPersister->expects($this->once())
            ->method('get')
            ->with($this->equalTo($collection), $this->equalTo(0))
            ->will($this->returnValue($element));

        self::assertEquals($element, $persister->get($collection, 0));
    }
}