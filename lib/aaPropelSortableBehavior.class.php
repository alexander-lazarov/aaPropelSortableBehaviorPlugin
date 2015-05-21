<?php
/**
 * This behaviors adds the operations to make the rows of the table orderable.
 *
 * To enable this behavior add this after model class declaration:
 * <code>
 *   sfPropelBehavior::add('ClassName', array('thumbnail'=>array(
 *      'column' => ClassNamePeer::COLUMN_NAME,
 *      'scope'  => ClassNamePeer::SCOPE_COLUMN_NAME
 *   )));
 * </code>
 *
 * The usage for the columns is the following:
 *
 * column : Model column holding rank value. The type of the column must be integer
 * scope (optional) : Model column holding row's scope id. The scope is used to differenciate lists in
 * the same table
 *
 * To integrate with admin generator insert the following code:
 * <code>
 * // generator.yml
      list:
        peer_method:  doSelectOrderByRank
        object_actions:
          move_up: ~
          move_down: ~
          _delete: ~
          _edit: ~

 * // actions.class.php
  public function executeListMoveUp(sfWebRequest $request ) {
    try {
      $this->Event = $this->getRoute()->getObject();
    }
    catch(  sfError404Exception $exception ) {
      $this->redirect( '@event' );
    }

    if( $this->Event->moveUp() ) {
      $this->getUser()->setFlash('notice', 'The event was moved successfully.');
    }
    else {
      $this->getUser()->setFlash('notice', 'The event cannot be moved up, it is already the first one.');
    }

    $this->redirect( '@event' );
  }

  public function executeListMoveDown(sfWebRequest $request ) {
    try {
      $this->Event = $this->getRoute()->getObject();
    }
    catch(  sfError404Exception $exception ) {
      $this->redirect( '@event' );
    }

    if( $this->Event->moveDown() ) {
      $this->getUser()->setFlash('notice', 'The event was moved successfully.');
    }
    else {
      $this->getUser()->setFlash('notice', 'The event cannot be moved up, it is already the first one.');
    }

    $this->redirect( '@event' );
  }

  // EventPeer.php
  public static function doSelectOrderByRank( Criteria $criteria, PropelPDO $con = null ) {
    $criteria->addAscendingOrderByColumn( self::RANK );
    return parent::doSelect( $criteria, $con );
  }

  // view.yml
  stylesheets:    [/aaPropelSortableBehaviorPlugin/css/main.css]
 * </code>
 * @author Alexander Lazarov           <alexander.lazaroff@gmail.com>
 */

class aaPropelSortableBehavior {

  /**
   * Gets the field name to be used for rank
   * @throws Exception if column is not set
   * @param BaseObject $object
   * @param $type BasePeer::TYPE_COLNAME or BasePeer::TYPE_FIELDNAME or BasePeer::TYPE_PHPNAME @see
   * BasePeer for more info
   * @return string
   */
  protected static function getRankColumn( BaseObject $object, $type = BasePeer::TYPE_COLNAME ) {
    $class = get_class( $object );
    $peerClass = get_class($object->getPeer());
    $option = 'propel_behavior_sortable_'.$class.'_column';

    $result = sfConfig::get( $option );
    if( !$result ) {
      throw new Exception('Required option '.$option.' not set');
    }

    return call_user_func(
      array($peerClass, 'translateFieldName'),
      $result,
      BasePeer::TYPE_COLNAME,
      $type
    );
  }


  /**
   * Gets the field name to be used for scope
   * @param BaseObject $object
   * @param $type BasePeer::TYPE_COLNAME or BasePeer::TYPE_FIELDNAME or BasePeer::TYPE_PHPNAME @see
   * BasePeer for more info
   * @return string
   */
  protected static function getScopeColumn( BaseObject $object, $type = BasePeer::TYPE_COLNAME ) {
    $class = get_class( $object );
    $peerClass = get_class($object->getPeer());
    $option = 'propel_behavior_sortable_'.$class.'_scope';

    $result = sfConfig::get( $option );

    if( $result )
      return call_user_func(
        array($peerClass, 'translateFieldName'),
        $result,
        BasePeer::TYPE_COLNAME,
        $type
      );
    else
      return null;
  }

  /**
   * Moves the object up or down 1 position
   * @param BaseObject $object
   * @param string $dir 'up' or 'down'
   * @param PropelPDO $con
   * @return boolean true if object had to be updated otherwise false
   */
  protected function move( BaseObject $object, $dir, PropelPDO $con = null ) {
    if( null === $con ) {
      $con = self::getConnection( $object );
    }

    $rankColumn = self::getRankColumn( $object, BasePeer::TYPE_PHPNAME);
    $scopeColumn = self::getScopeColumn( $object, BasePeer::TYPE_PHPNAME );

    $finderObj = DbFinder::From(get_class($object), $con)->
                where($rankColumn, 'up'==$dir?'<':'>', $object->getRank())->
                orderBy($rankColumn, 'up'==$dir?'desc':'asc');

    if( $scopeColumn && call_user_func(array($object, 'get'.$scopeColumn ) )) {
      $finderObj->where( $scopeColumn, call_user_func( array($object, 'get'.$scopeColumn )) );
    }

    $target = $finderObj->findOne();

    if( $target) {
      // swap rank values

      try {
        $con->beginTransaction();

        $temp = call_user_func( array($target, 'get'.$rankColumn ) );
        call_user_func(
          array($target, 'set'.$rankColumn ),
          call_user_func( array($object, 'get'.$rankColumn ) )
        );
        call_user_func(
          array($object, 'set'.$rankColumn ),
          $temp
        );

        $object->save( $con );
        $target->save( $con );

        $con->commit();

        if( method_exists( $object, 'clearCache' ) ) {
          $object->clearCache();
          $target->clearCache();
        }

      } catch( Exception $e ) {
        $con->rollback();
        throw $e;
      }

      return true;
    }
    else {
      return false;
    }


  }

  /**
   * Moves the object up 1 position
   * @param BaseObject $object
   * @param PropelPDO $con
   * @return boolean true if object had to be updated otherwise false
   */
  public function moveUp( BaseObject $object, PropelPDO $con = null ) {
    return self::move( $object, 'up', $con );
  }

  /**
   * Moves the object down 1 position
   * @param BaseObject $object
   * @param PropelPDO $con
   * @return boolean true if object had to be updated otherwise false
   */
  public function moveDown( BaseObject $object, PropelPDO $con = null ) {
    return self::move( $object, 'down', $con );
  }

  /**
   * Updates the rank column if object is new and the rank value is not set
   * @param BaseObject $object
   * @param PropelPDO $con
   */
  public function preSave( BaseObject $object, PropelPDO $con = null ) {
    $rankColumn = self::getRankColumn( $object, BasePeer::TYPE_PHPNAME);

    if(
      ( $object->isNew() && !call_user_func( array( $object, 'get'.$rankColumn ) ) )
      ||
      ( self::getScopeColumn( $object ) && $object->isColumnModified( self::getScopeColumn( $object ) ) )
    ) {
      call_user_func(
        array( $object, 'set'.$rankColumn ),
        self::getMaxRankCol( $object, $con ) + 1
      );
    }
  }


 /**
   * Returns the max value of rank column of the given object's scope
   * @param BaseObject $object
   * @param PropelPDO $con
   * @return int
   */
  protected static function getMaxRankCol( BaseObject $object, PropelPDO $con = null ) {
    $rankColumn = self::getRankColumn( $object, BasePeer::TYPE_PHPNAME);
    $scopeColumn = self::getScopeColumn( $object, BasePeer::TYPE_PHPNAME );

    $query = 'SELECT MAX(%s) as __rank__ FROM %s';
    $query = sprintf(
      $query,
      self::getRankColumn( $object, BasePeer::TYPE_FIELDNAME),
      self::getTableName( $object )
    );
    if( $scopeColumn && call_user_func( array($object, 'get'.$scopeColumn ) ) ) {
      $condition = ' WHERE %s=%s';
      $condition = sprintf(
        $condition,
        self::getScopeColumn( $object, BasePeer::TYPE_FIELDNAME ),
        call_user_func( array($object, 'get'.$scopeColumn ) )
      );

      $query .= $condition;
    }

    $con = self::getConnection( $object );
    $statement = $con->prepare( $query );
    $statement->execute( );

    $row = $statement->fetch( );
    if( $row ) {
      return $row['__rank__'];
    }
    else {
      return 0;
    }
 }


  /**
   * Gets the connection associated with $object
   * @param BaseObject $object
   * @return PropelPDO connection
   */
  protected static function getConnection( BaseObject $object ) {
    $peerClass = $object->getPeer();
    // use reflection class to retrieve class constant to make the code compatiable with PHP<5.3.x
    $ReflectionObject = new ReflectionClass( $peerClass );
    return Propel::getConnection( $ReflectionObject->getConstant( 'DATABASE_NAME' ) );
  }

  /**
   * Gets the table name of $object
   * @param BaseObject $object
   * @return String
   */
  protected static function getTableName( BaseObject $object ) {
    $peerClass = $object->getPeer();
    // use reflection class to retrieve class constant to make the code compatiable with PHP<5.3.x
    $ReflectionObject = new ReflectionClass( $peerClass );
    return $ReflectionObject->getConstant( 'TABLE_NAME' );
  }




}
