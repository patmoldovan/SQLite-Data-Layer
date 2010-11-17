<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
class Mino{
	var $handle;
	var $match;
	var $dbname;
	public function __construct($dbn="_temp"){
		// OPENS / CREATES database in *root*/_dbdata/
		// Set handle of the open file.
		$sr=$_SERVER['DOCUMENT_ROOT'];
		$dir="/_dbdata";
		if(!is_dir($sr.$dir)) mkdir($sr.$dir);
		$sdb=$sr.$dir."/".$dbn;
		$this->handle=sqlite_open($sdb);
		$this->dbname=$sdb;
	}
	function save($table,$data){
		// SAVES data to table 
		//Creates table if doesn't exist
		// data is serialized to 1 string.  will be unserialized later.
		// if first tuple exists new data is merged.
		// DATA will be REPLACED on identical KEYs
		$cnt=0;
		if(!$data) return ;
		$this->findkey($table,$data);
		if($this->match==0 ) return; // for no matches when 'idx'=XX
		if($this->match==-1){
			$dat=serialize($data);
			//*
			// this routine is due to sqlite 2 , 'IF NOT EXIST' only available in sqlite3
			do{
				if($cnt++ > 10) break;  // Only loop to 10 incase something goes wrong
				sqlite_exec($this->handle,"INSERT INTO $table (data1) VALUES ('$dat')",$sqliteerr);
				if($sqliteerr){
					if($sqliteerr=="no such table: $table"){
						$strCreate="CREATE TABLE $table (idx integer primary key, data1 text)";
						sqlite_exec($this->handle,$strCreate,$err1);
						if($err1) echo "Error Creating Table:<br><b>".$err1."</b><br>";	
					}
				}
				$idd=sqlite_last_insert_rowid($this->handle);				
			} while($sqliteerr);
			//*/
		} else {
			$arr=$this->match;
			$d1=array_shift($data);  // First element is find only;
			foreach($arr as $id){
				$copy=$this->getbyid($table,$id);
				//echo "COPY $id <pre style='background-color:#c0ffee'>";print_r($copy);echo "</pre>";
				//echo "DATA $id <pre style='background-color:#c0ffee'>";print_r($data);echo "</pre>";
				$newarr=array_merge($copy,$data);
				$dat=serialize($newarr);
				$str="UPDATE $table SET data1= '$dat' WHERE idx=".$id;
				sqlite_exec($this->handle,$str,$err1);
				if($err1) echo "ERR1:".$err1."<br>";
			}
		}
		//$this->getall($table);	
		$idd=sqlite_last_insert_rowid($this->handle);		
		return $idd;
	}

	function getall($tab){
		// GETALL Records
		$str="SELECT idx, data1 FROM $tab";
		$arr=sqlite_array_query($this->handle,$str,SQLITE_ASSOC);
		/*
		foreach($arr as $key=>$row){
			foreach($row as $f=>$d){
				if($f=='data1'){
					$arr[$key]['data1']=unserialize($d);
				}
			}
		}
		//*/
	
		foreach($arr as $key=>$row){
			$idq['idx']=$row['idx'];
			$temp=unserialize($row['data1']);
					/*	
					echo "<pre  style='background-color:#999999;color:black'>$key:";
					print_r($temp);
					echo "</pre>";	
					//*/				
			
			$t[]=array_merge($idq,$temp);
					/*	
					echo "<pre  style='background-color:#999999;color:black'>$key:";
					print_r($t);
					echo "</pre>";	
					//*/	
		}
		return $t;
	}
	function add($table,$data){
		//ADDNEW data to existing to form an array 
		// DATA will be APPENDED to array on identical KEYs
		$this->findkey($table,$data);  // findkey does not return a value
		$arrid=$this->match;
		// $arrid is either -1 (if no match) or array of idx (if Match)
		if($arrid==0) return;  // do not allow save a null idx
		if($arrid==-1) $this->save($table,$data);
		$d1=array_shift($data);	// first element of array is key to find	
		
		if(is_array($arrid)){
			foreach($arrid as $id){  // pat , chris , yukari  in AD
				$datamod=$data;  //reset data for each individual
				$copy=$this->getbyid($table,$id);   // returns only data1
				foreach($data as $key=>$valu){ //level=>one
					$kd=array();
					if(is_array($copy[$key])){	// old data ["jobs"] currently not array // IS level a current key for user
						foreach($copy[$key] as $job1){
							$kd[]=$job1;		// if is key kd=array of existing levels(actually = copy[key])
						}
					} else {
						$kd[]=$copy[$key];		// if not array (ie single value of not a key)
					}
					if(is_array($valu)){
						foreach($valu as $job1){
							$kd[]=$job1;
						}
					} else {
						$kd[]=$valu;
					}
			
					$datamod[$key]=$kd;

					$newar=array_merge($copy,$datamod);
					// send to clean function to save record
					$this->clean($table,$newar);
				}
			}
		}
	}
	private function findkey($table,$data){
		// finds existing records that match the first tuple of data.
		// sets $this->match to array of all matching records 
		// This no longer returns any data
		$olddata=$this->getall($table); // returns without data1
		foreach($data as $k1=>$v1){
			break;
		}
		unset($idxarr);
		$this->match=-1;

		if($k1=='idx'){
			// checks to make sure this is valid record.
			if(!$v1) {
				$this->match=0;
				return;
			}
			$q=$this->getbyid($table,$v1);
			if(is_array($q)){
				$arrx[]=$v1;
				$this->match=$arrx;
			}
		
		} else {
			foreach($olddata as $ii=>$arri){	
				if($arri[$k1]==$v1 ||(is_array($arri[$k1]) && in_array($v1,$arri[$k1]))) $idxarr[]=$arri['idx'];
			}
			if(is_array($idxarr)) $this->match=$idxarr;
		}		
	}
	private function clean($table,$arr1){
		// REMOVES blank records and duplicates from data
		// if only 1 record exists in array , array is removed to single value
		// Acts as save function for 'add' function
		$arr1=array_filter($arr1);
		foreach($arr1 as $kx=>$vx){
			if(is_array($vx)){
				$vx=array_unique($vx);
				$vx=array_filter($vx);
				if(count($vx)==1){
					$vx=array_values($vx);
					$arr1[$kx]=$vx[0];
				} else {
					$arr1[$kx]=$vx;
				}
			}
		}
		$this->save($table,$arr1);
	}
	function find($table,$qdata){
		
		// RETURNS a multi Dimensional array of all data meeting the criteria
		// SETS $this->match to array of all matches
		$return=array();
		$temp=array();
		$tempsize=0;
		$idc=-1;
		$this->match = -1;
		foreach($qdata as $k1=>$v1){
			$idc++;
			$temp[$idc][]=$idc; // seed array in case on no matches;
			$full=$this->getall($table);
			foreach($full as $id=>$val){   // loop through entire DB `id` = rowid / val= array (idx ,data1)			
				if($val[$k1]){
					if($val[$k1]==$v1 || (is_array($val[$k1]) && in_array($v1,$val[$k1]))) $temp[$idc][]=serialize($val);
				}
			}
			if(count($temp[$idc])>1) $x=array_shift($temp[$idc]); // remove first seed element only if match other elements;
		}
		
		/*	
		echo "<pre  style='background-color:#999999;color:black'>";
		print_r($temp);
		echo "</pre>";	
		//*/		
		if(count($temp)>1){
			$arr0=$temp[0];
			for($i=1;$i<count($temp);$i++){
				$arr0 = array_intersect($temp[$i],$arr0);
			}
			foreach($arr0 as $k3=>$sdata){
				$t2[$k3]=unserialize($sdata);
				$idxarr[]=$t2[$k3]['idx'];
			}
			$return = $t2;
		} else {
			//single match on return
			$return = array(unserialize($temp[0][0]));
			$idxarr[]=$return[0]['idx'];
		}
		$idxarr=array_unique($idxarr);

		if(is_array($idxarr)) $this->match = $idxarr;			
		return $return;
	}
	function getbyid($table,$id){
		// RETURNS  array of 'data1'  from a specific record identified by idx number
		$str="SELECT idx, data1 FROM $table where idx = $id";
		$arr=sqlite_array_query($this->handle,$str,SQLITE_ASSOC); //one record only

		$idq['idx']=$arr[0]['idx'];
		$arrrec=unserialize($arr[0]['data1']);
		if($idq){
			$reconly=array_merge($idq,$arrrec);
		} else {
			$reconly = null;
		}
		return $reconly;		
	}
	function delete($table,$qdata){
		$this->findkey($table,$qdata);
		$arrId=$this->match;
		var_dump($arrId);
		if($arrId==-1) return true;
		foreach($arrId as $id){
			$strDele="DELETE FROM $table WHERE idx = $id";
			sqlite_exec($this->handle,$strDele,$err1);
			if($err1) echo "ERR1:".$err1."<br>";
		}
	}
	function remove($table,$id,$data){  // $data[ph]=5033364613
		$arrQ=$this->getbyid($table,$id); //arr of Pat,  data only	
		if(!$arrQ) return;  // does as check to ensure id is valid
		foreach($data as $kq=>$vq){
			foreach($arrQ as $key=>$val){
				if($key==$kq){		// ifound the key	
					if($vq==$val){
						$arrQ=array_diff($arrQ,$data);
					} else if (is_array($val) && in_array($vq,$val) ) {
						$ar1[]=$vq;
						$val=array_diff($val,$ar1);
						$arrQ[$key]=$val;
					}
				}
			}
		}
		
		$dat=serialize($arrQ);
		$str="UPDATE $table SET data1= '$dat' WHERE idx=".$id;
		sqlite_exec($this->handle,$str,$err1);
		if($err1) echo "ERR1:".$err1."<br>$str";		
		$this->clean($table,$arrQ);
	}
	function destroy(){
		sqlite_close($this->handle);
		unlink($this->dbname);
	}
}
?>