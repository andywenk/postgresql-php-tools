<?PHP
/**
 * PgSQL.php
 * 
 * Copyright (c) 2009, Andreas Wenk
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *	* Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *	* Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *	* Neither the name of the author nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * PHP5 is required for the use of this class
 *
 * @author Andreas Wenk
 * @link http://www.netzmeister-st-pauli.de
 * @category Library
 * @package PgSQL
 * @version 0.0.1
 * @link http://github.com/
 */
class PgSQL {
	
	public $dbh;
	public $fields = 0;
	public $rows = 0;
	private $sql = array();

	/**
	 * constructor
	 * 
	 * @return void
	 */
	public function __construct() { }
	
	/**
	 * connect to the postgresql database
	 *
	 * @param string $host
	 * @param string $database
	 * @param string $user
	 * @param string $pass
	 * @uses self::error_string()
	 * @return $this->dbh
	 */
	public function connect($host, $port, $database, $user, $pass) {
		$this->dbh = pg_connect("host=".$host." port=".$port." dbname=".$database." user=".$user." password=".$pass."");

		try {
			if(!$this->dbh) {
				throw new Exception('connect(): unable to establish database connection');
			}
		} catch (Exception $e) {
			self::error_string($e);
		}
								
		return $this->dbh;	
	}
	
	/**
	 * close database connection
	 *
	 * @uses self::error_string()
	 * @return void
	 */
	public function close() {
		try {
			if(!pg_free_result($this->dbh)) {
				throw new Exception('close(): unable to free database result');
			}

			if(!pg_close($this->dbh)) {
				throw new Exception('close(): unable to close connection to database');
			}
		} catch (Exception $e) {
			self::error_string($e);
		}
	}
	
	/**
	 * transmit a select statement against the database. The returned
	 * result is an associative array
	 *
	 * @param string $qs
	 * @uses self::error_string()
	 * @return array $result
	 */
	public function select($qs) { 
		$res = array(); 
		$i = 0;
		
		try {
			$query = pg_query($this->dbh, $qs);

			if(!$query){
                throw new Exception('select(): ' .$qs);
            } else {
                while($this->row = pg_fetch_assoc($query)) {
                    $res[$i] = $this->row;
                    $i++;
                }
            }
		} catch (Exception $e) {
            $res = array();
			self::error_string($e);
            return false;
		}

		$this->fields = pg_num_fields($query); 
		$this->rows = pg_num_rows($query); 
		
		return $res;
	}

	/**
	 * transmit a select statement against the database. The returned
	 * result is an indexed array
	 *
	 * @param string $qs;
	 * @uses self::error_string()
	 * @return array $res
	 */
	public function select_row($qs) {
		$res = array();
		$i = 0;

		try {
			$query = pg_query($this->dbh, $qs);

			if(!$query) {
                throw new Exception('select_row(): ' .$qs);
            } else {
                while($this->row = pg_fetch_row($query)) {
                    $res[$i] = $this->row;
                    $i++;
                }
            }
		} catch (Exception $e) {
            $res = array();
			self::error_string($e);
            return false;
		}

		$this->fields = pg_num_fields($query);
		$this->rows = pg_num_rows($query);

		return $res;
	}
	
	/**
	 * transmit a select statement against the database. The returned
	 * result is an simple array. This method can be used if you are
	 * sure, that the result is just one row
	 *
	 * @param string $qs
	 * @uses self::error_string()
	 * @return array $result
	 */
	public function select_simple($qs) { 
		$res = array(); 
		
		try {
			$query = pg_query($this->dbh, $qs);

			if(!$query) {
                throw new Exception('select_simple(): ' .$qs);
            } else {
                $res = pg_fetch_assoc($query);
            }
		} catch (Exception $e) {
			$res = array();
			self::error_string($e);
			return false;
		}
		
		$this->fields = pg_num_fields($query); 
		$this->rows = pg_num_rows($query); 
		
		return $res;
	}
		
	/**
	 * add a query string to the sql array. The sql array
	 * is used within transaction()
	 *
	 * @param string $qs
	 * @return void
	 */
	public function sql_add($qs) {	
		array_push($this->sql, $qs);
	}

	/**
	 * unset the sql array used in transaction()
	 *
	 * @return void
	 */
	public function clear_sql_arr() {			
		unset($this->sql);
		$this->sql = array();
	}
			
	/**
	 * transmit a transaction against the database. Any count of statements
	 * saved in $this->sql will be executed. If one fails, the whole
	 * transaction will be rolled back.
	 *
	 * @uses self::error_string()
	 * @return bool true | false
	 */
	public function transaction() {
		$ok = 1;
		self::begin();

		try {
			while ($sql = array_shift($this->sql)) {
				if(!pg_query($this->dbh, $sql)){
					self::rollback();
					throw new Exception("transaction(): the transaction faild");
				}
			}
		} catch (Exception $e) {
			self::error_string($e);
			return false;
		}
		
		self::commit();
		self::clear_sql_arr();
			
		return true;
	}
	
	/**
	 * send BEGIN for the start of a transaction
	 *
	 * @return bool true | false
	 */
	public function begin() {
		return pg_query($this->dbh, 'BEGIN') ? true : false;
	}
	
	/**
	 * send COMMIT for end of a transaction
	 *
	 * @return bool true | false
	 */
	public function commit() {
		return pg_query($this->dbh, 'COMMIT') ? true : false;
	}
	
	/**
	 * send ROLLBACK to interupt a transaction
	 *
	 * @return bool true | false
	 */
	public function rollback() {
		return pg_query($this->dbh, 'ROLLBACK') ? true : false;
	}
	
	/**
	 * prepare a statement and send it to the database
	 *
	 * @param string $qs
	 * @param string $stmt_name
	 * @uses self::error_string()
	 * @return bool true | false
	 */
	public function prepare($qs, $stmt_name) {
		try {
			if(!pg_connection_busy($this->dbh)) {
				pg_send_prepare($this->dbh, $stmt_name, $qs);

				try {
					if(!pg_get_result($this->dbh)) {
						throw new Exception ('prepare(): prepare was not successfull');
					}
				} catch (Exception $e) {
					self::error_string($e);
				}
			} else {
				throw new Exception ('prepare(): connection is busy ');
			}
		} catch (Exception $e) {
			self::error_string($e);
		}

		return true;
	}

	/**
	 * execute a prepared statement
	 *
	 * @param string $stmt_name
	 * @param array $values
	 * @uses self::error_string()
	 * @return bool true / false
	 */
	public function execute($stmt_name, array $values) {
		try {
			if(!pg_connection_busy($this->dbh)) {
				if($res != pg_execute($this->dbh, $stmt_name, $values)){
					throw new Exception ('execute(): executing prepared statement failed');
				}

				return pg_fetch_all($res);
			} else {
				throw new Exception ('execute(): connection is busy ');
			}
		} catch (Exception $e) {
			self::error_string($e);
		}

		return true;
	}

	/**
	 * return the count of fields in the result
	 * of the statement
	 *
	 * @return int $this->fields
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * return the count of rows in the result
	 * of the statement
	 *
	 * @return int $this->rows
	 */
	public function get_rows() {
		return $this->rows;
	}

	/**
	 * transmit a ping against the database
	 *
	 * @return bool true | false
	 */
	public function ping() {
		return (pg_ping($this->dbh)) ? true : false;
	}

	/**
	 * get the database handle
	 *
	 * @return $this->dbh
	 */
	public function dbh() {
		return $this->dbh;
	}

	/**
	 * escaped an string
	 *
	 * @param string $str
	 * @return string $str
	 */
	static public function quote($str) {
		return pg_escape_string(stripslashes($str));
	}

	/**
	 * output the error message. Expects the Exception object
	 *
	 * @param object $e
	 */
	private function error_string($e) {
		echo "<table cellpadding=\"4\" cellspacing=\"4\" width=\"98%\" 
					 style=\"border: 1px solid #990000;background-color:#eee;
					 margin:10px;\">
			  <tr>
				<td><b style=\"color:#990000;\"><u>db error:</u><b></td>
				<td>".$e->getMessage()."</td>
			  </tr>
			  <tr>
			  	<td><b style=\"color:#990000;\"><u>file:</u><b></td>
				<td>".$e->getFile()." [line ".$e->getLine()."]</td>
			  </tr>
			  <tr>
			  	<td><b style=\"color:#990000;\"><u>db message:</u><b></td>
				<td>".pg_last_error($this->dbh)."</td>
			  </tr>
			</table>";
	}
}
?>