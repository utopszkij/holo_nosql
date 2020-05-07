<?php
declare(strict_types=1);
include_once './btree.php';

use PHPUnit\Framework\TestCase;

global $btree;
// test Cases
class BtreeTest extends TestCase {
    public function test_start() {
        global $btree;
        $btree = new Btree();
    	$btree->clear();
		$btree->insert('20', 'V20'); 
		$btree->insert('25', 'V25'); 
		$btree->insert('21', 'V21'); 
		$btree->insert('20/2', 'V20');
		$btree->insert('10', 'V10'); 
		$btree->insert('08', 'V08'); 
		$btree->insert('12', 'V12'); 
		$btree->insert('13', 'V13'); 
		$btree->insert('14', 'V14'); 
		$btree->insert('15', 'V15'); 
		$btree->insert('123', 'C15');
		$btree->insert('124', 'D10');
		$count = $btree->count();    	   
        $this->AssertEquals(12,$count);
   } 
   
   public function test_find_ok() {
       global $btree;
       $res = $btree->find('C15'); $this->assertEquals('C15', $res->value); $this->assertEquals('123', $res->key);
       $res = $btree->find('V20'); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
   }
   
   public function test_find_notfound() {
       global $btree;
       $res = $btree->find('C1sasa5');
       $this->assertTrue($res->deleted);
   }
   
   public function test_first() {
       global $btree;
       $res = $btree->first();
       $this->assertEquals('C15', $res->value);
       $this->assertEquals('123', $res->key);
   }
   
   public function test_next() {
       global $btree;
       $res = $btree->first(); $this->assertEquals('C15', $res->value); $this->assertEquals('123', $res->key);
       $res = $btree->next($res); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->next($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->next($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->next($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->next($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->next($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->next($res); $this->assertEquals('V15', $res->value); $this->assertEquals('15', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->next($res); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->next($res); $this->assertEquals('V25', $res->value); $this->assertEquals('25', $res->key);
       $res = $btree->next($res); $this->assertTrue($res->deleted);
   }
   
   public function test_last_previos() {
       global $btree;
       $res = $btree->last(); $this->assertEquals('V25', $res->value); $this->assertEquals('25', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V15', $res->value); $this->assertEquals('15', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->previos($res); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->previos($res); $this->assertEquals('C15', $res->value); $this->assertEquals('123', $res->key);
       $res = $btree->previos($res); $this->assertTrue($res->deleted);
   }
   
   public function test_delete_middle() {
       global $btree;
       $item = $btree->find('V15');
       $btree->delete($item);
       
       $count = $btree->count();
       $this->AssertEquals(11, $count);
       
       $res = $btree->find('V15'); $this->AssertTrue($res->deleted);
       
       $res = $btree->find('C15'); $this->AssertFalse($res->deleted);
       $res = $btree->find('V20'); $this->AssertFalse($res->deleted);
       $res = $btree->find('V08'); $this->AssertFalse($res->deleted);
       $res = $btree->find('V25'); $this->AssertFalse($res->deleted);
       
       $res = $btree->first(); $this->assertEquals('C15', $res->value); $this->assertEquals('123', $res->key);
       $res = $btree->next($res); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->next($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->next($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->next($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->next($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->next($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->next($res); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->next($res); $this->assertEquals('V25', $res->value); $this->assertEquals('25', $res->key);
       $res = $btree->next($res); $this->assertTrue($res->deleted);
       
       $res = $btree->last(); $this->assertEquals('V25', $res->value); $this->assertEquals('25', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->previos($res); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->previos($res); $this->assertEquals('C15', $res->value); $this->assertEquals('123', $res->key);
       $res = $btree->previos($res); $this->assertTrue($res->deleted);
   }
   
   public function test_delete_first() {
       global $btree;
       $item = $btree->find('C15');
       $btree->delete($item);
       
       $count = $btree->count();
       $this->AssertEquals(10, $count);
       
       $res = $btree->find('C15'); $this->AssertTrue($res->deleted);
       
       $res = $btree->find('V20'); $this->AssertFalse($res->deleted);
       $res = $btree->find('V08'); $this->AssertFalse($res->deleted);
       $res = $btree->find('V25'); $this->AssertFalse($res->deleted);
       
       $res = $btree->first(); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->next($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->next($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->next($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->next($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->next($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->next($res); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->next($res); $this->assertEquals('V25', $res->value); $this->assertEquals('25', $res->key);
       $res = $btree->next($res); $this->assertTrue($res->deleted);
       
       $res = $btree->last(); $this->assertEquals('V25', $res->value); $this->assertEquals('25', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->previos($res); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->previos($res); $this->assertTrue($res->deleted);
   }
      
   public function test_delete_last() {
       global $btree;
       $item = $btree->find('V25');
       $btree->delete($item);
       
       $count = $btree->count();  $this->AssertEquals(9, $count);
       
       $res = $btree->find('V25'); $this->AssertTrue($res->deleted);
       
       $res = $btree->find('V20'); $this->AssertFalse($res->deleted);
       $res = $btree->find('V08'); $this->AssertFalse($res->deleted);
       
       $res = $btree->first(); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->next($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->next($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->next($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->next($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->next($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->next($res); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->next($res); $this->assertTrue($res->deleted);
       
       $res = $btree->last(); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->previos($res); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->previos($res); $this->assertTrue($res->deleted);
   }
   
   public function test_inser_middle() {
       global $btree;
       $btree->insert('17','V17');
       $count = $btree->count();  $this->AssertEquals(10, $count);
       $res = $btree->find('V17'); $this->AssertEquals('17',$res->key);
       
       $res = $btree->first(); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->next($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->next($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->next($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->next($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->next($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->next($res); $this->assertEquals('V17', $res->value); $this->assertEquals('17', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->next($res); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->next($res); $this->assertTrue($res->deleted);
   }
   
   public function test_insert_first() {
       global $btree;
       $btree->insert('15','C15');
       $count = $btree->count();  $this->AssertEquals(11, $count);
       $res = $btree->find('C15'); $this->AssertEquals('15',$res->key);
       
       $res = $btree->first(); $this->assertEquals('C15', $res->value); $this->assertEquals('15', $res->key);
       $res = $btree->next($res); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->next($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->next($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->next($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->next($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->next($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->next($res); $this->assertEquals('V17', $res->value); $this->assertEquals('17', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->next($res); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->next($res); $this->assertTrue($res->deleted);
       
       $res = $btree->last(); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V17', $res->value); $this->assertEquals('17', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->previos($res); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->previos($res); $this->assertEquals('C15', $res->value); $this->assertEquals('15', $res->key);
       $res = $btree->previos($res); $this->assertTrue($res->deleted);
   }
   
   public function test_delete_from_double() {
       global $btree;
       $item = $btree->find('V20'); $btree->delete($item);
       $count = $btree->count();  $this->AssertEquals(10, $count);
       $res = $btree->find('V20'); $this->AssertEquals('20/2',$res->key);
       
       $res = $btree->first(); $this->assertEquals('C15', $res->value); $this->assertEquals('15', $res->key);
       $res = $btree->next($res); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->next($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->next($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->next($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->next($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->next($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->next($res); $this->assertEquals('V17', $res->value); $this->assertEquals('17', $res->key);
       $res = $btree->next($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->next($res); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->next($res); $this->assertTrue($res->deleted);
       
       $res = $btree->last(); $this->assertEquals('V21', $res->value); $this->assertEquals('21', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V20', $res->value); $this->assertEquals('20/2', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V17', $res->value); $this->assertEquals('17', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V14', $res->value); $this->assertEquals('14', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V13', $res->value); $this->assertEquals('13', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V12', $res->value); $this->assertEquals('12', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V10', $res->value); $this->assertEquals('10', $res->key);
       $res = $btree->previos($res); $this->assertEquals('V08', $res->value); $this->assertEquals('08', $res->key);
       $res = $btree->previos($res); $this->assertEquals('D10', $res->value); $this->assertEquals('124', $res->key);
       $res = $btree->previos($res); $this->assertEquals('C15', $res->value); $this->assertEquals('15', $res->key);
       $res = $btree->previos($res); $this->assertTrue($res->deleted);
   }
   
}

?>