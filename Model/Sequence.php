<?php

declare(strict_types=1);

namespace Marissen\BetterOrderIncrementing\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection as AppResource;
use Magento\Framework\DB\Sequence\SequenceInterface;
use Magento\SalesSequence\Model\Meta;
use Magento\SalesSequence\Model\Profile;
use Psr\Log\LoggerInterface;

class Sequence implements SequenceInterface
{
    /**
     * Default pattern for Sequence
     */
    const DEFAULT_PATTERN = "%s%'.09d%s";

    const CONFIG_PATH_ENABLE_DEBUG = 'sales/id_incrementer/enable_debug';

    /**
     * @var string
     */
    private $lastIncrementId;
    /**
     * @var Meta
     */
    private $meta;
    /**
     * @var false|\Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connection;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var string
     */
    private $pattern;

    /**
     * @param Meta $meta
     * @param AppResource $resource
     * @param string $pattern
     */
    public function __construct(
        Meta $meta,
        AppResource $resource,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        $pattern = self::DEFAULT_PATTERN
    ) {
        $this->meta = $meta;
        $this->connection = $resource->getConnection('sales');
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->pattern = $pattern;
    }

    /**
     * Retrieve current sequence value.
     *
     * @return string
     */
    public function getCurrentValue()
    {
        if (!isset($this->lastIncrementId)) {
            return null;
        }

        return sprintf(
            $this->pattern,
            $this->getMetaActiveProfile()->getData('prefix'),
            $this->calculateCurrentValue(),
            $this->getMetaActiveProfile()->getData('suffix')
        );
    }

    /**
     * Retrieve next sequence value.
     *
     * @return string
     * @throws \Zend_Db_Statement_Exception
     */
    public function getNextValue()
    {
        $current = $this->getLatestId();
        $next = $current + 1;

        if ((bool) $this->scopeConfig->getValue(self::CONFIG_PATH_ENABLE_DEBUG)) {
            $this->logger->info(
                sprintf(
                    'Increment table %s (%d => %d)',
                    $this->getMetaSequenceTable(),
                    $current,
                    $next
                ),
                [
                    'module' => 'Marissen_BetterOrderIncrementing'
                ]
            );
        }

        $this->connection->insert(
            $this->getMetaSequenceTable(),
            [
                'sequence_value' => $next
            ]
        );

        $this->lastIncrementId = $this->getLatestId();

        return $this->getCurrentValue();
    }

    /**
     * Returns the latest id from the sequence table.
     *
     * @return int
     * @throws \Zend_Db_Statement_Exception
     */
    private function getLatestId(): int
    {
        $select = $this->connection->select()
            ->from($this->getMetaSequenceTable())
            ->columns('sequence_value')
            ->order('sequence_value ' . \Magento\Framework\DB\Select::SQL_DESC)
            ->limit(1);

        $lastRow = $this->connection->query($select)->fetch();

        return is_array($lastRow) ? (int) $lastRow['sequence_value'] : 0;
    }

    /**
     * Returns current value based on step and start value.
     *
     * @return string
     */
    private function calculateCurrentValue()
    {
        return ($this->lastIncrementId - $this->getMetaActiveProfile()->getData('start_value'))
            * $this->getMetaActiveProfile()->getData('step') + $this->getMetaActiveProfile()->getData('start_value');
    }

    /**
     * Returns the active profile from the sequence meta.
     *
     * @return \Magento\SalesSequence\Model\Profile
     */
    private function getMetaActiveProfile(): Profile
    {
        return $this->meta->getData('active_profile');
    }

    /**
     * Returns the sequence table from the sequence meta.
     *
     * @return string
     */
    private function getMetaSequenceTable(): string
    {
        return $this->meta->getData('sequence_table');
    }
}
