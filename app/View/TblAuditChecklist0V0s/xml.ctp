<?php if($tblAuditChecklist0V0s){
    	foreach ($tblAuditChecklist0V0s as $tblAuditChecklist0V0):    
$def = $tblAuditChecklist0V0['TblAuditChecklist0V0']['checklist_title'];
			$result["TblAuditChecklist0V0"]["ChecklistTitle"] = $tblAuditChecklist0V0['TblAuditChecklist0V0']['checklist_title'];
			
            if($tblAuditChecklist0V0['TblAuditChecklist0V0']['date_added'])$dt =   date(Configure::read('dateFormat'),strtotime($tblAuditChecklist0V0['TblAuditChecklist0V0']['date_added'])) ;
            else $dt =  "--";$result["TblAuditChecklist0V0"]["DateAdded"] = $dt;
			$result["TblAuditChecklist0V0"]["AddedBy"] = $tblAuditChecklist0V0['AddedBy']['name'];
			$result["TblAuditChecklist0V0"]["Comment"] = $tblAuditChecklist0V0['TblAuditChecklist0V0']['comments'];
        foreach($linkedTables as $linkedTable){
                $res = $this->requestAction(array('controller'=>$linkedTable['CustomTable']['table_name'], 'action'=>'xml',$tblAuditChecklist0V0['TblAuditChecklist0V0']['id'],$linkedTable['CustomTable']['id'])); 
                $result[Inflector::classify($linkedTable['CustomTable']['table_name'])] = $res;
        }

			$result["TblAuditChecklist0V0"]["PreparedBy"] = $tblAuditChecklist0V0["PreparedBy"]["name"];
			$result["TblAuditChecklist0V0"]["ApprovedBy"] = $tblAuditChecklist0V0["ApprovedBy"]["name"];if($tblAuditChecklist0V0['TblAuditChecklist0V0']['comments'])$dt =   date(Configure::read('dateTimeFormat'),strtotime($tblAuditChecklist0V0['TblAuditChecklist0V0']['created'])) ;
    else $dt =  "--";
			$result["TblAuditChecklist0V0"]["Created"] = $dt;
$results[str_replace(' ','',$def)] = $result;
	endforeach; 
} ?>
<?php
    $returnjson['results']['result'] = $results;
    $returnjson['results']['paging'] = $this->Paginator->request->params['paging'];
    $xml = Xml::fromArray($returnjson, array('pretty' => true));
    $this->response->body($xml->asXML());
    $this->response->statusCode(200);
    $this->response->type('xml');
    $this->response->send();
    exit;   
?>