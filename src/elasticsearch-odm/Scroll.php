<?php
namespace Galexth\ElasticsearchOdm;

/**
 * Scroll Iterator.
 *
 * @author Manuel Andreo Garcia <andreo.garcia@gmail.com>
 *
 * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-scroll.html
 */
class Scroll extends \Elastica\Scroll
{
    /**
     * @var Model
     */
    protected $_model;

    /**
     * Scroll constructor.
     *
     * @param \Elastica\Search          $search
     * @param \Galexth\ElasticsearchOdm\Model $model
     * @param string                    $expiryTime
     */
    public function __construct(\Elastica\Search $search, Model $model, $expiryTime = '1m')
    {
        parent::__construct($search, $expiryTime);

        $this->_model = $model;
    }

    /**
     * Returns current result set.
     *
     * @link http://php.net/manual/en/iterator.current.php
     *
     * @return \Galexth\ElasticsearchOdm\Collection
     */
    public function current()
    {
        $models = $this->_model::hydrate($this->_currentResultSet->getResults(), true);

        return $this->_model->newCollection($models, $this->_currentResultSet);
    }

    /**
     * Returns true if current result set contains at least one hit.
     *
     * @link http://php.net/manual/en/iterator.valid.php
     *
     * @return bool
     */
    public function valid()
    {
        return $this->_nextScrollId !== null && $this->_currentResultSet->count();
    }
}
