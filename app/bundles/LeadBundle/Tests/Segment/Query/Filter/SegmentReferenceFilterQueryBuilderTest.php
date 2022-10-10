<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\Segment\Query\Filter;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Provider\FilterOperatorProviderInterface;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\ContactSegmentFilterCrate;
use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Mautic\LeadBundle\Segment\ContactSegmentFilterOperator;
use Mautic\LeadBundle\Segment\Decorator\BaseDecorator;
use Mautic\LeadBundle\Segment\Exception\SegmentNotFoundException;
use Mautic\LeadBundle\Segment\Query\ContactSegmentQueryBuilder;
use Mautic\LeadBundle\Segment\Query\Filter\FilterQueryBuilderInterface;
use Mautic\LeadBundle\Segment\Query\Filter\SegmentReferenceFilterQueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Mautic\LeadBundle\Segment\RandomParameterName;
use Mautic\LeadBundle\Segment\TableSchemaColumnsCache;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SegmentReferenceFilterQueryBuilderTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    /**
     * @var MockObject|RandomParameterName
     */
    private $randomParameterMock;

    /**
     * @var MockObject|EventDispatcherInterface
     */
    private $dispatcherMock;

    /**
     * @var Connection|MockObject
     */
    private $connectionMock;

    /**
     * @var SegmentReferenceFilterQueryBuilder
     */
    private $queryBuilder;

    /**
     * @var LeadList
     */
    private $segment;

    public function setUp(): void
    {
        parent::setUp();
        defined('MAUTIC_TABLE_PREFIX') or define('MAUTIC_TABLE_PREFIX', '');
        $this->randomParameterMock = $this->createMock(RandomParameterName::class);
        $this->dispatcherMock      = $this->createMock(EventDispatcherInterface::class);
        $this->connectionMock      = $this->createMock(Connection::class);

        $this->queryBuilder        = new SegmentReferenceFilterQueryBuilder(
            $this->randomParameterMock,
            new ContactSegmentQueryBuilder($this->em, $this->randomParameterMock, $this->dispatcherMock),
            $this->em,
            $this->createMock(ContactSegmentFilterFactory::class),
            $this->dispatcherMock
        );

        $this->connectionMock->method('quote')
            ->willReturnArgument(0);

        $this->segment = $this->createNewSegment();
    }

    public function testGetServiceId(): void
    {
        $this->assertEquals(
            'mautic.lead.query.builder.special.leadlist',
            $this->queryBuilder::getServiceId()
        );
    }

    /**
     * @return array<mixed>
     */
    public function dataApplyQuery(): iterable
    {
        yield ['eq', "SELECT 1 FROM __PREFIX__leads l WHERE EXISTS(SELECT null FROM __PREFIX__leads queryAlias WHERE (l.id = queryAlias.id) AND (EXISTS(SELECT null FROM __PREFIX__lead_lists_leads para1 WHERE (queryAlias.id = para1.lead_id) AND ((para1.leadlist_id = %s) AND ((para1.manually_added = 1) OR (para1.manually_removed = ''))))))"];
        yield ['neq', "SELECT 1 FROM __PREFIX__leads l WHERE EXISTS(SELECT null FROM __PREFIX__leads queryAlias WHERE (l.id = queryAlias.id) AND (EXISTS(SELECT null FROM __PREFIX__lead_lists_leads para1 WHERE (queryAlias.id = para1.lead_id) AND ((para1.leadlist_id = %s) AND ((para1.manually_added = 1) OR (para1.manually_removed = ''))))))"];
    }

    /**
     * @dataProvider dataApplyQuery
     */
    public function testApplyQuery(string $operator, string $expectedQuery): void
    {
        $expectedQuery = str_replace('__PREFIX__', MAUTIC_TABLE_PREFIX, $expectedQuery);
        $queryBuilder  = new QueryBuilder($this->connectionMock);
        $queryBuilder->select('1');
        $queryBuilder->from(MAUTIC_TABLE_PREFIX.'leads', 'l');

        $filter = $this->getContactSegmentFilter($operator, (string) $this->segment->getId());

        $this->randomParameterMock->method('generateRandomParameterName')
            ->willReturnOnConsecutiveCalls('queryAlias', 'para1', 'para2');

        $this->queryBuilder->applyQuery($queryBuilder, $filter);

        Assert::assertSame(sprintf($expectedQuery, $this->segment->getId()), $queryBuilder->getDebugOutput());
    }

    public function testApplyQueryWhenSegmentNotExist(): void
    {
        $queryBuilder = new QueryBuilder($this->connectionMock);
        $queryBuilder->select('1');
        $queryBuilder->from(MAUTIC_TABLE_PREFIX.'leads', 'l');

        $filter = $this->getContactSegmentFilter('eq', 'non_exist_segment_id');

        $this->randomParameterMock->method('generateRandomParameterName')
            ->willReturnOnConsecutiveCalls('queryAlias', 'para1', 'para2');

        $this->expectException(SegmentNotFoundException::class);
        $this->queryBuilder->applyQuery($queryBuilder, $filter);
    }

    private function createNewSegment(): LeadList
    {
        $segment = new LeadList();
        $segment->setName('Test Segment');
        $segment->setAlias('test_segment');
        $segment->isPublished(true);
        $segment->setPublicName('Test Segment');

        $this->em->persist($segment);
        $this->em->flush();

        return $segment;
    }

    private function getContactSegmentFilter(string $operator, string $parameterValue): ContactSegmentFilter
    {
        return new ContactSegmentFilter(
            new ContactSegmentFilterCrate(
                [
                    'object'     => 'lead',
                    'glue'       => 'and',
                    'field'      => 'leadlist',
                    'type'       => 'leadlist',
                    'operator'   => $operator,
                    'properties' => [
                            'filter' => [
                                    0 => $parameterValue,
                                ],
                        ],
                    'filter' => [
                            0 => $parameterValue,
                        ],
                    'display' => null,
                ]
            ),
            new BaseDecorator(new ContactSegmentFilterOperator(
                $this->createMock(FilterOperatorProviderInterface::class)
            )),
            new TableSchemaColumnsCache($this->createMock(EntityManager::class)),
            $this->createMock(FilterQueryBuilderInterface::class)
        );
    }
}
