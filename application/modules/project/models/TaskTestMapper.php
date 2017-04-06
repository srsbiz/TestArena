<?php
/*
Copyright © 2014 TaskTestArena 

This file is part of TaskTestArena.

TaskTestArena is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

The full text of the GPL is in the LICENSE file.
*/
class Project_Model_TaskTestMapper extends Custom_Model_Mapper_Abstract
{
  protected $_dbTableClass = 'Project_Model_TaskTestDbTable';
  
  public function getByTask(Application_Model_Task $task)
  {
    $rows = $this->_getDbTable()->getByTask($task->getId());
    
    if (null === $rows)
    {
      return false;
    }
    
    $list = array();

    foreach ($rows->toArray() as $row)
    {
      $list[] = new Application_Model_TaskTest($row);
    }

    return $list;
  }
  
  public function add(Application_Model_TaskTest $taskTest)
  {
    try
    {
      $taskTest->setId($this->_getDbTable()->insert(array(
        'task_id' => $taskTest->getTask()->getId(),
        'test_id' => $taskTest->getTest()->getId()
      )));
      return true;
    }
    catch (Exception $e)
    {
      Zend_Registry::get('Zend_Log')->log($e->getMessage(), Zend_Log::ERR);
      return false;
    }
  }
  
  public function delete(Application_Model_TaskTest $taskTest)
  {
    try
    {
      $this->_getDbTable()->delete(array(
        'task_id = ?' => $taskTest->getTask()->getId(),
        'test_id = ?' => $taskTest->getTest()->getId()
      ));
      return true;
    }
    catch (Exception $e)
    {
      Zend_Registry::get('Zend_Log')->log($e->getMessage(), Zend_Log::ERR);
      return false;
    }
  }
  
  public function getForView(Application_Model_TaskTest $taskTest)
  {
    $row = $this->_getDbTable()->getForView($taskTest->getTask()->getId(), $taskTest->getTest()->getId());
    
    if (null === $row)
    {
      return false;
    }
    
    return $taskTest->setDbProperties($row->toArray());
  }
  
  public function getOtherTestForView(Application_Model_TaskTest $taskTest)
  {
    $row = $this->_getDbTable()->getOtherTestForView($taskTest->getTask()->getId(), $taskTest->getTest()->getId());
    
    if (null === $row)
    {
      return false;
    }
    
    return $taskTest->setDbProperties($row->toArray());
  }
  
  public function getTestCaseForView(Application_Model_TaskTest $taskTest)
  {
    $row = $this->_getDbTable()->getTestCaseForView($taskTest->getTask()->getId(), $taskTest->getTest()->getId());
    
    if (null === $row)
    {
      return false;
    }
    
    $taskTest->setTestObject();
    return $taskTest->setDbProperties($row->toArray());
  }
  
  public function getExploratoryTestForView(Application_Model_TaskTest $taskTest)
  {
    $row = $this->_getDbTable()->getExploratoryTestForView($taskTest->getTask()->getId(), $taskTest->getTest()->getId());
    
    if (null === $row)
    {
      return false;
    }
    
    $taskTest->setTestObject();
    return $taskTest->setDbProperties($row->toArray());
  }
  
  public function changeResolution(Application_Model_TaskTest $taskTest)
  {
    $db = $this->_getDbTable();
    $adapter = $db->getAdapter();
    
    $data = array(
      'resolution_id' => $taskTest->getResolutionId()
    );
    
    $where = array(
      'task_id = ?' => $taskTest->getTask()->getId(),
      'test_id = ?' => $taskTest->getTest()->getId()
    );

    try
    {
      $adapter->beginTransaction();
      $db->update($data, $where);
      
      $commentContent = Utils_Text::unicodeTrim($taskTest->getExtraData('comment'));
      
      if (!empty($commentContent))
      {
        $comment = new Application_Model_Comment();
        $comment->setContent($commentContent);
        $comment->setUserObject($taskTest->getTask()->getAssignee());
        $comment->setSubjectId($taskTest->getTask()->getId());
        $comment->setSubjectType(Application_Model_CommentSubjectType::TASK);
        $commentMapper = new Application_Model_CommentMapper();

        if ($commentMapper->add($comment) === false)
        {
          throw new Exception('[Task:ResolveTest] Comment adding is failed');
        }
      }

      return $adapter->commit();
    }
    catch (Exception $e)
    {
      Zend_Registry::get('Zend_Log')->log($e->getMessage(), Zend_Log::ERR);
      $adapter->rollBack();
      return false;
    }
  }
  
  public function saveGroup(array $taskTests)
  {
    $db = $this->_getDbTable();
    $adapter = $db->getAdapter();
    
    $data = array();
    $values = implode(',', array_fill(0, count($taskTests), '(?, ?, ?)'));
    
    foreach ($taskTests as $taskTest)
    {
      $data[] = $taskTest->getTask()->getId();
      $data[] = $taskTest->getTest()->getId();
      $data[] = $taskTest->getResolutionId();
    }
    
    $statement = $adapter->prepare('INSERT INTO '.$db->getName().' (task_id, test_id, resolution_id) VALUES '.$values);
    return $statement->execute($data);
  }
}