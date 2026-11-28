<?php
/**
 * Application model for Cake.
 *
 * This file is application-wide model file. You can put all
 * application-wide model-related methods here.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Model
 * @since         CakePHP(tm) v 0.2.9
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses("Model", "Model");
App::uses('ClassRegistry', 'Utility');
App::uses('ConnectionManager', 'Model');
/**
 * Application model for Cake.
 *
 * Add your application-wide methods in the class below, your models
 * will inherit them.
 *
 * @package       app.Model
 */
class AppModel extends Model
{
    var $actsAs = ["WhoDidIt"];

    public function beforeSave($options = [])
    {        
        foreach ($this->data[$this->alias] as $key => $value) {
            if($value && !is_array($value))$this->data[$this->alias][$key] = ltrim(rtrim($value));
        }        
    }

    public function beforeFind($query)
    {
        
    }

    public function afterSave($created, $options = []){

    }

    public function beforeDelete($cascade = true) {
        $this->deletedId = $this->id;
        return true;
    }

    public function afterDelete(){
        if($this->alias == 'Template' || $this->alias == 'File' ){
            // delete files
            if($this->alias == 'Template'){
                $path = Configure::read('files') . DS . 'templates'. DS . $this->id;
                $folder = new Folder($path);
                if($this->id)$folder->delete();    
            }
            
            if($this->alias == 'File'){
                $path = Configure::read('files') . DS . 'files'. DS . $this->id;
                $folder = new Folder($path);
                if($this->id)$folder->delete();
            }

            if($this->alias == 'CustomTable'){
                $path = Configure::read('files') . DS . 'custom_tables'. DS . $this->id;
                $folder = new Folder($path);
                if($this->id)$folder->delete();
            }

            if($this->alias == 'QcDocument'){
                $path = Configure::read('files') . DS . 'qc_documents'. DS . $this->id;
                $folder = new Folder($path);
                if($this->id)$folder->delete();                
            }

            if($this->alias == 'Process'){
                $path = Configure::read('files') . DS . 'processes'. DS . $this->id;
                $folder = new Folder($path);
                if($this->id)$folder->delete();
            }

            if($this->alias == 'PdfTemplate'){
                $path = Configure::read('files') . DS . 'pdf_template'. DS . $this->id;
                $folder = new Folder($path);
                if($this->id)$folder->delete();
            }
        }

        if($this->alias == 'CustomTable'){
            // delete graph panels
            $this->loadmodel('GraphPanel');
            $this->GraphPanel->deleteAll(array('GraphPanel.custom_table_id'=>$this->id));
        }

        if($this->alias == 'Standard'){
            $deletearray = array(
                'Clause',
                'QcDocument',
                'QcDocumentCategory'             
            );

            foreach($deletearray as $delmodel){
                $this->loadmodel($delmodel);
                debug($delmodel);
                $this->$delmodel->deleteAll(array($delmodel.'.standard_id'=>$this->id));
            }            
        }

        if($this->alias == 'QcDocument'){
            $deletearray = array(
                'ChildQcDocument'                
            );

            foreach($deletearray as $delmodel){
                $this->loadmodel($delmodel);
                $this->$delmodel->deleteAll(array($delmodel.'.qc_document_id'=>$this->id));
            }                
        }

        if($this->alias == 'File'){
            $path = Configure::read('files') . DS . 'files'. DS . $this->id;
            $folder = new Folder($path);
            if($this->id)$folder->delete();
        }

        // delete approvals and related records                
        $this->Approval = ClassRegistry::init('Approval');
        $this->Approval->deleteAll(array('Approval.record' => $this->deletedId,'Approval.model_name'=>$this->alias));
    }    

    // ===== M9 review (owner review 12 Nov 2026): a GET or HEAD request does not change stored data =====
    // FlinkISO changed data while pages were opened (custom_tables/view saved the form on every visit, pdf_templates/add_cover
    // created rows, and others). Earlier corrections listed such actions by name; this rule does not depend on a list. During a
    // GET or HEAD request every model write (save, saveField, saveAll, updateAll, delete, deleteAll, and query() with anything
    // other than a read) is refused and logged to tmp/logs/m9_access.log as refused_get_write, for every user, administrators
    // included. What a page view may still write is listed here in full, because the application needs it to work:
    //   1. histories: a new row (the audit trail records every request).
    //   2. user_sessions.end_time and users.login_status: the session's last-activity time and the logout flag.
    //   3. file_key, pre_file_key, file_status, data_received on an existing row: the document editor's session key, renewed
    //      when a document or form record is opened. Written directly to those columns only; modified and modified_by are
    //      not touched, and any other column in the same save is left as stored (logged if it differed).
    //   4. files: a new row with record_id 'tmp' (the editor's working copy made when a form's add page is opened), and the
    //      removal of such a draft row or of a row whose folder holds no file (FlinkISO's own clean-up of its working copies).
    //   5. cake_sessions (only if sessions are kept in the database).
    // M9_GET_GUARD is 'enforce' in every delivered file. 'observe' exists only in the staging test copy: it logs what the rule
    // would do (observed_get_write) and changes nothing, so the list above could be checked against real page views first.
    const M9_GET_GUARD = 'enforce';

    // ===== Owner's fifth review (16 Nov 2026), decision 395: no table structure change during a request =====
    // query() refuses any statement that creates, alters, drops, renames or truncates a table, on every request (any method) and
    // for every user, unless the code issuing it has marked itself (AppModel::$m9Ddl). Two places do: the form builder's commit and
    // undo steps (CustomTablesController, decision 392) and the form delete actions (custom_tables delete, delete_child), which drop
    // the form's own table. Each refusal is logged as refused_ddl. All DDL of the application goes through query(); its other direct
    // database calls are reads.
    public static $m9Ddl = 0;

    public static function m9_is_ddl($sql) {
        if (!is_string($sql)) return false;
        $s = preg_replace('#/\*.*?\*/|--[^\n]*|\#[^\n]*#s', ' ', $sql);
        return preg_match('/(^|;)\s*\(?\s*(alter|create|drop|rename|truncate)\b/i', $s) === 1;
    }
    public static $m9Pass = 0;

    public function _m9_get_active() {
        if (self::$m9Pass > 0) return false;
        $m = strtoupper((string)env('REQUEST_METHOD'));
        if ($m !== 'GET' && $m !== 'HEAD') return false;
        return $this->_m9_get_table() !== 'cake_sessions';
    }

    public function _m9_get_table() {
        return is_string($this->useTable) && $this->useTable !== '' ? $this->useTable : (string)$this->table;
    }

    public function _m9_get_log($event, $detail) {
        $ca = '?/?'; $kind = 'page'; $uid = '';
        try {
            $r = class_exists('Router') ? Router::getRequest(true) : null;
            if ($r) { $ca = (isset($r->params['controller']) ? $r->params['controller'] : '?') . '/' . (isset($r->params['action']) ? $r->params['action'] : '?'); if ($r->is('ajax')) $kind = 'ajax'; }
            if (class_exists('CakeSession')) $uid = substr((string)CakeSession::read('User.id'), 0, 8);
        } catch (Exception $m9e) { }
        $line = implode("\t", array(date('Y-m-d H:i:s'), $uid, $ca, $kind, strtoupper((string)env('REQUEST_METHOD')), $event, $detail));
        if (defined('LOGS')) @file_put_contents(LOGS . ($event === 'observed_get_write' ? 'm9_get_observe.log' : 'm9_access.log'), $line . "\n", FILE_APPEND | LOCK_EX);
    }

    // What may a save do during a GET? array(kind, columns to write, columns left as stored): kind is insert (allowed new row),
    // key (allowed columns only), none (nothing differs from the stored row) or refuse.
    public function _m9_get_save_plan() {
        $tb = $this->_m9_get_table();
        $row = (isset($this->data[$this->alias]) && is_array($this->data[$this->alias])) ? $this->data[$this->alias] : array();
        $id = $this->id; if (is_array($id)) $id = count($id) === 1 ? reset($id) : null;
        $cur = null;
        if ($id !== null && $id !== false && $id !== '') {
            $cur = $this->find('first', array('recursive' => -1, 'callbacks' => false, 'conditions' => array($this->alias . '.' . $this->primaryKey => $id)));
            $cur = empty($cur[$this->alias]) ? null : $cur[$this->alias];
        }
        if ($cur === null) {
            if ($tb === 'histories') return array('insert', array(), array());
            if ($tb === 'files' && isset($row['record_id']) && $row['record_id'] === 'tmp') return array('insert', array(), array());
            return array('refuse', array(), array('new row'));
        }
        $may = array('file_key', 'pre_file_key', 'file_status', 'data_received');
        if ($tb === 'user_sessions') $may[] = 'end_time';
        if ($tb === 'users') $may[] = 'login_status';
        $norm = function ($v) { if ($v === null) return ''; if (is_bool($v)) return $v ? '1' : '0'; return is_scalar($v) ? trim((string)$v) : null; };
        $apply = array(); $other = array();
        foreach ($row as $k => $v) {
            if ($k === $this->primaryKey || $k === 'modified' || $k === 'modified_by' || false === $this->hasField($k)) continue;
            $a = $norm($v); $b = $norm(array_key_exists($k, $cur) ? $cur[$k] : null);
            if ($a !== null && $a === $b) continue;
            if ($a !== null && in_array($k, $may, true)) $apply[$k] = $v; else $other[] = $k;
        }
        if ($apply) return array('key', $apply, $other);
        return array($other ? 'refuse' : 'none', array(), $other);
    }

    public function _m9_get_write_columns($id, $apply) {
        $db = $this->getDataSource(); $set = array(); $val = array();
        foreach ($apply as $k => $v) { $set[] = $db->name($k) . ' = ?'; $val[] = is_bool($v) ? (int)$v : $v; }
        $val[] = $id;
        $ok = $db->execute('UPDATE ' . $db->fullTableName($this) . ' SET ' . implode(', ', $set) . ' WHERE ' . $db->name($this->primaryKey) . ' = ?', array(), $val);
        if (method_exists($db, 'flushQueryCache')) $db->flushQueryCache();
        return (bool)$ok;
    }

    public function _m9_get_parent_save($data, $validate, $fieldList) {
        self::$m9Pass++;
        try { $r = parent::save($data, $validate, $fieldList); } finally { self::$m9Pass--; }
        return $r;
    }

    public function _m9_get_parent_delete($id, $cascade) {
        self::$m9Pass++;
        try { $r = parent::delete($id, $cascade); } finally { self::$m9Pass--; }
        return $r;
    }

    public function _m9_save_guarded($data = null, $validate = true, $fieldList = array()) {
        if (false === $this->_m9_get_active()) return parent::save($data, $validate, $fieldList);
        if ($data) $this->set($data);
        // FlinkISO's copy of CakePHP (lib/Cake/Model/Model.php, set()) ignores the data of a second save on the same model object made
        // without create() in between: that save writes nothing and returns false on any method. The same here, before anything is read.
        if (false === is_array($this->data) || empty($this->data[$this->alias]) || false === is_array($this->data[$this->alias])) return false;
        $tb = $this->_m9_get_table(); $plan = $this->_m9_get_save_plan(); $id = $this->id; if (is_array($id)) $id = reset($id);
        if (self::M9_GET_GUARD === 'observe') {
            $this->_m9_get_log('observed_get_write', $tb . ' save ' . $plan[0] . ($plan[1] ? ' write:' . implode(',', array_keys($plan[1])) : '') . ($plan[2] ? ' left:' . implode(',', $plan[2]) : ''));
            return $this->_m9_get_parent_save($data, $validate, $fieldList);
        }
        if ($plan[0] === 'insert') return $this->_m9_get_parent_save($data, $validate, $fieldList);
        if ($plan[2]) $this->_m9_get_log('refused_get_write', $tb . ' save:' . implode(',', $plan[2]));
        if ($plan[0] === 'refuse') return false;
        if ($plan[0] === 'key' && false === $this->_m9_get_write_columns($id, $plan[1])) return false;
        return empty($this->data) ? true : $this->data;
    }

    public function _m9_updateAll_guarded($fields, $conditions = true) {
        if (false === $this->_m9_get_active()) return parent::updateAll($fields, $conditions);
        if (self::M9_GET_GUARD === 'observe') { $this->_m9_get_log('observed_get_write', $this->_m9_get_table() . ' updateAll refuse'); return parent::updateAll($fields, $conditions); }
        $this->_m9_get_log('refused_get_write', $this->_m9_get_table() . ' updateAll');
        return false;
    }

    public function _m9_delete_guarded($id = null, $cascade = true) {
        if (false === $this->_m9_get_active()) return parent::delete($id, $cascade);
        $tb = $this->_m9_get_table(); $one = ($id === null || $id === false || $id === '' || $id === array()) ? $this->id : $id; if (is_array($one)) $one = count($one) === 1 ? reset($one) : null;
        $row = null;
        if ($one !== null && $one !== false && $one !== '') {
            $row = $this->find('first', array('recursive' => -1, 'callbacks' => false, 'conditions' => array($this->alias . '.' . $this->primaryKey => $one)));
            $row = empty($row[$this->alias]) ? null : $row[$this->alias];
        }
        $ok = false;
        if ($row !== null && $tb === 'files') {
            if (isset($row['record_id']) && $row['record_id'] === 'tmp') $ok = true;
            else {
                $dir = (string)Configure::read('files') . DS . 'files' . DS . $one; $has = false;
                if (Configure::read('files') && is_dir($dir)) foreach (scandir($dir) as $x) if ($x !== '.' && $x !== '..' && is_file($dir . DS . $x)) { $has = true; break; }
                $ok = (bool)Configure::read('files') && false === $has;
            }
        }
        if (self::M9_GET_GUARD === 'observe') {
            if ($row !== null) $this->_m9_get_log('observed_get_write', $tb . ' delete ' . ($ok ? 'allow' : 'refuse'));
            return $this->_m9_get_parent_delete($id, $cascade);
        }
        if ($row === null) return false;
        if ($ok) return $this->_m9_get_parent_delete($id, $cascade);
        $this->_m9_get_log('refused_get_write', $tb . ' delete');
        return false;
    }

    public function _m9_deleteAll_guarded($conditions, $cascade = true, $callbacks = false) {
        if (false === $this->_m9_get_active()) return parent::deleteAll($conditions, $cascade, $callbacks);
        if (self::M9_GET_GUARD === 'observe') { $this->_m9_get_log('observed_get_write', $this->_m9_get_table() . ' deleteAll refuse'); return parent::deleteAll($conditions, $cascade, $callbacks); }
        $this->_m9_get_log('refused_get_write', $this->_m9_get_table() . ' deleteAll');
        return false;
    }

    public function query($sql) {
        $m9a = func_get_args();
        self::m9_jr_raw_check($sql); // decision 397: a row write by query() during a builder submit is not attributable
        if (self::$m9Ddl < 1 && self::m9_is_ddl($sql)) { $this->_m9_get_log('refused_ddl', $this->_m9_get_table() . ' ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 80)); return false; }
        if ($this->_m9_get_active() && is_string($sql) && preg_match('/^[\s(]*(select|show|describe|desc|explain)\b/i', $sql) !== 1) {
            $verb = preg_match('/^[\s(]*([a-z]+)/i', $sql, $m9m) ? strtolower($m9m[1]) : '?';
            if (self::M9_GET_GUARD === 'observe') $this->_m9_get_log('observed_get_write', $this->_m9_get_table() . ' query ' . $verb . ' refuse');
            else { $this->_m9_get_log('refused_get_write', $this->_m9_get_table() . ' query:' . $verb); return false; }
        }
        return parent::query(...$m9a);
    }

    // ===== Owner's sixth review (17 Nov 2026), decision 397: a form-builder undo changes only the rows that submit wrote =====
    // Revision 9 kept every row of custom_tables and approval_processes at the start of a builder submit and, on failure, wrote
    // all of them back: a change another user made to any of those rows while the submit ran would have been overwritten.
    // From revision 10 nothing is kept in advance. While a builder submit is open (CustomTablesController::_m9_tx_begin) every
    // write this request makes through a model to the builder's tables (custom_tables, approval_processes, qc_documents) is
    // journalled, row by row: the row as it was just before this request first wrote it ('before') and the row as this request
    // left it ('last'). If another user changed the row between two writes of this request, 'before' becomes their version.
    // Undo (m9_journal_undo) then works on those rows only, each with one statement that applies only while the row is still
    // exactly as this request left it (every column compared, byte for byte):
    //   a row this submit added            DELETE ... WHERE id = ? AND every column as this submit left it
    //   a row this submit changed          UPDATE ... SET <row before> WHERE id = ? AND every column as this submit left it
    //   a row this submit deleted          INSERT <row before>, only if no row with that id exists again
    // A row another user changed or re-created after this submit wrote it is left as they made it and reported
    // (row_kept_changed / row_kept_recreated, builder_rollback_incomplete); a row another user deleted stays deleted. Rows this
    // submit never wrote are never read back, compared or written. A write the journal cannot attribute to rows (query() with
    // INSERT/UPDATE/DELETE/REPLACE on one of these tables, or updateAll/deleteAll whose rows cannot be listed) makes the commit
    // refuse (unjournaled_write) and is reported by the undo, never guessed. Writes to other tables during a submit are counted
    // (logged as other=) and, as in revision 9, not undone.
    public static $m9Journal = null;

    // M9 point 1: one write lock for every model write and for every file lock. It is independent of
    // the builder journal, so a normal request cannot bypass the lock that a builder submit holds.
    public static $m9WriteLockDepth = 0;
    public static $m9WriteLockName = '';
    public static $m9WriteFence = 0;

    public static function m9_write_lock_name() {
        $db = '';
        try { $db = (string)ConnectionManager::getDataSource('default')->getConnection()->query('SELECT DATABASE()')->fetchColumn(); } catch (Throwable $e) { }
        return 'm9w_' . substr(sha1($db === '' ? 'default' : $db), 0, 32);
    }

    public static function m9_write_lock_acquire($timeout = 10) {
        if (self::$m9WriteLockDepth > 0) { self::$m9WriteLockDepth++; return true; }
        try {
            $pdo = ConnectionManager::getDataSource('default')->getConnection();
            $name = self::m9_write_lock_name();
            $q = $pdo->prepare('SELECT GET_LOCK(?, ?)');
            $q->execute(array($name, max(1, (int)$timeout)));
            if ((string)$q->fetchColumn() !== '1') return false;
            self::$m9WriteLockName = $name;
            self::$m9WriteLockDepth = 1;
            return true;
        } catch (Throwable $e) { return false; }
    }

    public static function m9_write_lock_release() {
        if (self::$m9WriteLockDepth <= 0) return;
        self::$m9WriteLockDepth--;
        if (self::$m9WriteLockDepth > 0) return;
        try {
            $pdo = ConnectionManager::getDataSource('default')->getConnection();
            $q = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $q->execute(array(self::$m9WriteLockName));
        } catch (Throwable $e) { }
        self::$m9WriteLockName = '';
        self::$m9WriteFence = 0;
    }

    public static function m9_write_lock_depth() {
        return self::$m9WriteLockDepth;
    }

    // Held across the final row verification and RENAME TABLE. Every write path needs the same lock,
    // so this is an explicit fence on top of the already-held exclusive write lock.
    public static function m9_write_fence_enter() {
        if (self::$m9WriteLockDepth < 1) return false;
        self::$m9WriteFence++;
        return true;
    }

    public static function m9_write_fence_leave() {
        if (self::$m9WriteFence > 0) self::$m9WriteFence--;
    }

    public static function m9_write_fence_active() {
        return self::$m9WriteFence > 0;
    }

    public static function m9_journal_start($tables) {
        $t = array();
        foreach ((array)$tables as $x) if (is_string($x) && preg_match('/^[A-Za-z0-9_]{1,64}$/', $x) === 1) $t[$x] = true;
        self::$m9Journal = array('tables' => $t, 'rows' => array(), 'other' => array(), 'raw' => array());
        return true;
    }

    public static function m9_journal_stop() {
        $j = self::$m9Journal; self::$m9Journal = null;
        return $j;
    }

    public function _m9_jr_table() {
        return (string)$this->tablePrefix . $this->_m9_get_table();
    }

    // true when this write must be journalled; a write to any other table during a submit is counted
    public function _m9_jr_on() {
        if (!is_array(self::$m9Journal)) return false;
        $t = $this->_m9_jr_table();
        if (isset(self::$m9Journal['tables'][$t])) {
            if (preg_match('/^[A-Za-z0-9_]{1,64}$/', (string)$this->primaryKey) !== 1) { self::$m9Journal['raw'][] = $t . ' bad_key'; return false; }
            return true;
        }
        self::$m9Journal['other'][$t] = (isset(self::$m9Journal['other'][$t]) ? self::$m9Journal['other'][$t] : 0) + 1;
        return false;
    }

    public function _m9_jr_pdo() {
        return $this->getDataSource()->getConnection();
    }

    // the rows with these ids as stored now, read directly (no cache, no callbacks)
    public function _m9_jr_read($ids) {
        $o = array(); $t = $this->_m9_jr_table(); $pk = (string)$this->primaryKey;
        $ids = array_values(array_unique(array_map('strval', (array)$ids)));
        foreach (array_chunk($ids, 200) as $ch) {
            $q = $this->_m9_jr_pdo()->prepare('SELECT * FROM `' . $t . '` WHERE `' . $pk . '` IN (' . implode(',', array_fill(0, count($ch), '?')) . ')');
            $q->execute($ch);
            while ($r = $q->fetch(PDO::FETCH_ASSOC)) $o[(string)$r[$pk]] = $r;
        }
        return $o;
    }

    public function _m9_jr_key($id) {
        return $this->_m9_jr_table() . "\0" . (string)$id;
    }

    // just before a write to these rows
    public function _m9_jr_before($ids) {
        if (!$ids) return;
        $now = $this->_m9_jr_read($ids);
        foreach ($ids as $id) {
            $id = (string)$id; $k = $this->_m9_jr_key($id); $cur = isset($now[$id]) ? $now[$id] : null;
            if (!isset(self::$m9Journal['rows'][$k])) {
                self::$m9Journal['rows'][$k] = array('t' => $this->_m9_jr_table(), 'pk' => (string)$this->primaryKey, 'id' => $id, 'before' => $cur, 'last' => $cur, 'writes' => 0, 'rebased' => 0);
            } elseif (self::$m9Journal['rows'][$k]['last'] !== $cur) {
                // changed by someone else since this request last wrote it: their version is what an undo returns to
                self::$m9Journal['rows'][$k]['before'] = $cur; self::$m9Journal['rows'][$k]['rebased']++;
            }
        }
    }

    // just after a write to these rows (whether or not the write succeeded)
    public function _m9_jr_after($ids) {
        if (!$ids) return;
        $now = $this->_m9_jr_read($ids);
        foreach ($ids as $id) {
            $id = (string)$id; $k = $this->_m9_jr_key($id);
            if (!isset(self::$m9Journal['rows'][$k])) self::$m9Journal['rows'][$k] = array('t' => $this->_m9_jr_table(), 'pk' => (string)$this->primaryKey, 'id' => $id, 'before' => null, 'last' => null, 'writes' => 0, 'rebased' => 0);
            self::$m9Journal['rows'][$k]['last'] = isset($now[$id]) ? $now[$id] : null;
            self::$m9Journal['rows'][$k]['writes']++;
        }
    }

    // the row a save() is about to write, by CakePHP's own rule: the key in the data, else the model's current id; null = a new row
    public function _m9_jr_save_id($data) {
        $pk = (string)$this->primaryKey; $v = null;
        if (is_array($data)) {
            if (isset($data[$this->alias]) && is_array($data[$this->alias]) && array_key_exists($pk, $data[$this->alias])) $v = $data[$this->alias][$pk];
            elseif (array_key_exists($pk, $data) && !is_array($data[$pk])) $v = $data[$pk];
        }
        if ($v === null || $v === false || $v === '') $v = $this->id;
        if (is_array($v)) $v = count($v) === 1 ? reset($v) : null;
        return ($v === null || $v === false || $v === '') ? null : (string)$v;
    }

    // the ids of the rows updateAll/deleteAll will touch; null when they cannot be listed
    public function _m9_jr_ids($conditions) {
        try {
            $r = $this->find('all', array('conditions' => $conditions, 'fields' => array($this->alias . '.' . $this->primaryKey), 'recursive' => 0, 'callbacks' => false));
        } catch (Exception $e) { return null; }
        if (!is_array($r)) return null;
        $o = array(); foreach ($r as $x) if (isset($x[$this->alias][$this->primaryKey])) $o[] = (string)$x[$this->alias][$this->primaryKey];
        return $o;
    }

    public function save($data = null, $validate = true, $fieldList = array()) {
        if (false === self::m9_write_lock_acquire(10)) return false;
        try {
            if (false === $this->_m9_jr_on()) return $this->_m9_save_guarded($data, $validate, $fieldList);
            $id = $this->_m9_jr_save_id($data);
            if ($id !== null) $this->_m9_jr_before(array($id));
            try { $r = $this->_m9_save_guarded($data, $validate, $fieldList); }
            finally {
                $id2 = $id;
                if ($id2 === null) { $x = $this->id; if (is_array($x)) $x = count($x) === 1 ? reset($x) : null; if ($x !== null && $x !== false && $x !== '') $id2 = (string)$x; }
                if ($id2 !== null) $this->_m9_jr_after(array($id2));
            }
            return $r;
        } finally {
            self::m9_write_lock_release();
        }
    }

    public function saveAll($data = array(), $options = array()) {
        if (false === self::m9_write_lock_acquire(10)) return false;
        try { return parent::saveAll($data, $options); } finally { self::m9_write_lock_release(); }
    }

    public function saveMany($data = null, $options = array()) {
        if (false === self::m9_write_lock_acquire(10)) return false;
        try { return parent::saveMany($data, $options); } finally { self::m9_write_lock_release(); }
    }

    public function saveAssociated($data = null, $options = array()) {
        if (false === self::m9_write_lock_acquire(10)) return false;
        try { return parent::saveAssociated($data, $options); } finally { self::m9_write_lock_release(); }
    }

    public function updateAll($fields, $conditions = true) {
        if (false === self::m9_write_lock_acquire(10)) return false;
        try {
            if (false === $this->_m9_jr_on()) return $this->_m9_updateAll_guarded($fields, $conditions);
            // select the affected ids only while the write lock is held: no row can enter or leave
            // the condition set between this read and the update, so the journal cannot record the wrong rows
            $ids = $this->_m9_jr_ids($conditions);
            if ($ids === null) { self::$m9Journal['raw'][] = $this->_m9_jr_table() . ' updateAll'; return false; }
            $this->_m9_jr_before($ids);
            try { $r = $this->_m9_updateAll_guarded($fields, $conditions); } finally { $this->_m9_jr_after($ids); }
            return $r;
        } finally {
            self::m9_write_lock_release();
        }
    }

    public function delete($id = null, $cascade = true) {
        if (false === self::m9_write_lock_acquire(10)) return false;
        try {
            if (false === $this->_m9_jr_on()) return $this->_m9_delete_guarded($id, $cascade);
            $one = ($id === null || $id === false || $id === '' || $id === array()) ? $this->id : $id; if (is_array($one)) $one = count($one) === 1 ? reset($one) : null;
            $ids = ($one === null || $one === false || $one === '') ? array() : array((string)$one);
            $this->_m9_jr_before($ids);
            try { $r = $this->_m9_delete_guarded($id, $cascade); } finally { $this->_m9_jr_after($ids); }
            return $r;
        } finally {
            self::m9_write_lock_release();
        }
    }

    public function deleteAll($conditions, $cascade = true, $callbacks = false) {
        if (false === self::m9_write_lock_acquire(10)) return false;
        try {
            if (false === $this->_m9_jr_on()) return $this->_m9_deleteAll_guarded($conditions, $cascade, $callbacks);
            // select the affected ids only while the write lock is held: no row can enter or leave
            // the condition set between this read and the delete, so the journal cannot record the wrong rows
            $ids = $this->_m9_jr_ids($conditions);
            if ($ids === null) { self::$m9Journal['raw'][] = $this->_m9_jr_table() . ' deleteAll'; return false; }
            $this->_m9_jr_before($ids);
            try { $r = $this->_m9_deleteAll_guarded($conditions, $cascade, $callbacks); } finally { $this->_m9_jr_after($ids); }
            return $r;
        } finally {
            self::m9_write_lock_release();
        }
    }

    // query() with a row write naming one of the journalled tables, during a submit: cannot be attributed to rows
    public static function m9_jr_raw_check($sql) {
        if (!is_array(self::$m9Journal) || !is_string($sql)) return;
        $s = preg_replace('#/\*.*?\*/|--[^\n]*|\#[^\n]*#s', ' ', $sql);
        if (preg_match('/^[\s(]*(insert|update|delete|replace)\b/i', $s) !== 1) return;
        foreach (self::$m9Journal['tables'] as $t => $x) if (preg_match('/(^|[^A-Za-z0-9_])' . preg_quote($t, '/') . '([^A-Za-z0-9_]|$)/i', $s) === 1) self::$m9Journal['raw'][] = $t . ' query';
    }

    // CAST(`c` AS BINARY) <=> CAST(? AS BINARY) for every column of the row: NULL-safe and byte for byte
    public static function m9_jr_match($row) {
        $w = array(); $a = array();
        foreach ($row as $c => $v) { $w[] = 'CAST(`' . str_replace('`', '', $c) . '` AS BINARY) <=> CAST(? AS BINARY)'; $a[] = $v; }
        return array(implode(' AND ', $w), $a);
    }

    // Undo of the journalled rows (see above). Adds to $bad what could not be undone; $info gets the rows left to another
    // user's later change. Returns the number of rows put back.
    public static function m9_journal_undo($pdo, &$bad, &$info) {
        $j = self::$m9Journal; self::$m9Journal = null; $n = 0;
        if (!is_array($j)) return 0;
        foreach (array_unique($j['raw']) as $x) $bad[] = 'unjournaled_write:' . str_replace(' ', '_', $x);
        $read = function ($t, $pk, $id) use ($pdo) { $q = $pdo->prepare('SELECT * FROM `' . $t . '` WHERE `' . $pk . '` = ?'); $q->execute(array($id)); $r = $q->fetch(PDO::FETCH_ASSOC); return $r === false ? null : $r; };
        foreach (array_reverse($j['rows'], true) as $e) {
            $t = $e['t']; $pk = $e['pk']; $id = $e['id']; $b = $e['before']; $l = $e['last']; $tag = $t . ':' . substr($id, 0, 8);
            if ($b === $l) continue;
            try {
                if ($l === null) {
                    if ($read($t, $pk, $id) !== null) { $bad[] = 'row_kept_recreated:' . $tag; continue; }
                    $c = array_map(function ($k) { return '`' . str_replace('`', '', $k) . '`'; }, array_keys($b));
                    $q = $pdo->prepare('INSERT INTO `' . $t . '` (' . implode(', ', $c) . ') VALUES (' . implode(', ', array_fill(0, count($b), '?')) . ')');
                    try { $q->execute(array_values($b)); } catch (Exception $x) { if ($read($t, $pk, $id) !== null) { $bad[] = 'row_kept_recreated:' . $tag; continue; } throw $x; }
                    if ($q->rowCount() === 1) $n++;
                } else {
                    list($w, $wa) = self::m9_jr_match($l);
                    if ($b === null) {
                        $sql = 'DELETE FROM `' . $t . '` WHERE `' . $pk . '` = ? AND ' . $w; $args = array_merge(array($id), $wa);
                    } else {
                        $set = array(); $sa = array();
                        foreach ($b as $c => $v) { if ((string)$c === $pk) continue; $set[] = '`' . str_replace('`', '', $c) . '` = ?'; $sa[] = $v; }
                        $sql = 'UPDATE `' . $t . '` SET ' . implode(', ', $set) . ' WHERE `' . $pk . '` = ? AND ' . $w; $args = array_merge($sa, array($id), $wa);
                    }
                    $q = $pdo->prepare($sql); $q->execute($args);
                    if ($q->rowCount() !== 1) {
                        $cur = $read($t, $pk, $id);
                        if ($cur === null) { $info[] = 'row_deleted_by_other:' . $tag; continue; }
                        if ($cur !== $b) { $bad[] = 'row_kept_changed:' . $tag; continue; }
                    } else $n++;
                }
                // verification of this row: it is now exactly as before this submit
                $cur = $read($t, $pk, $id);
                if ($cur !== $b) $bad[] = 'row_not_restored:' . $tag;
            } catch (Exception $x) { $bad[] = 'row_error:' . $tag; }
        }
        return $n;
    }

    public static function m9_journal_summary($j) {
        if (!is_array($j)) return 'rows=0';
        $w = 0; foreach ($j['rows'] as $e) if ($e['before'] !== $e['last']) $w++;
        $o = array(); foreach ($j['other'] as $t => $c) $o[] = $t . ':' . $c;
        return 'rows=' . $w . ($o ? ' other=' . implode(',', $o) : '');
    }
}
