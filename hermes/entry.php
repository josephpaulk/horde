<?php
/**
 * Copyright 2002-2014 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 * @author Jan Schneider <jan@horde.org>
 */

require_once __DIR__ . '/lib/Application.php';
Horde_Registry::appInit('hermes');


if (Hermes::showAjaxView()) {
    Horde::url('', true)->setAnchor('time')->redirect();
}

$vars = Horde_Variables::getDefaultVariables();
if (!$vars->exists('id') && $vars->exists('timer')) {
    $timer_id = $vars->get('timer');
    try {
        $timer = Hermes::getTimer($timer_id);
        $tname = $timer['name'];
        $tformat = $prefs->getValue('twentyFour') ? 'G:i' : 'g:i a';
        $elapsed = ((!$timer['paused']) ? time() - $timer['time'] : 0 ) + $timer['elapsed'];
        $vars->set('hours', round((float)$elapsed / 3600, 2));
        if ($prefs->getValue('add_description')) {
            $vars->set('note', sprintf(_("Using the \"%s\" stop watch from %s to %s"), $tname, date($tformat, $timer_id), date($tformat, time())));
        }
        $notification->push(sprintf(_("The stop watch \"%s\" has been stopped."), $tname), 'horde.success');
        if ($timers = @unserialize($prefs->getValue('running_timers'))) {
            unset($timers[$timer_id]);
            $prefs->setValue('running_timers', serialize($timers));
        }
    } catch (Horde_Exception_NotFound $e) {}
}

switch ($vars->get('formname')) {
case 'hermes_form_time_entry':
    $url = $vars->get('url');
    if (empty($url)) {
        $url = Horde::url('time.php');
    }
    try {
        $form = new Hermes_Form_Time_Entry($vars);
    } catch (Hermes_Exception $e) {
            $notification->push(sprintf(_("There was an error storing your timesheet: %s"), $e->getMessage()), 'horde.error');
            header('Location: ' . $url);
            exit;
    }
    if ($form->validate($vars)) {
        $form->getInfo($vars, $info);
        try {
            if ($vars->exists('id')) {
                $notification->push(_("Your time was successfully updated."), 'horde.success', array('sticky'));
                $GLOBALS['injector']->getInstance('Hermes_Driver')->updateTime(array($info));
            } else {
                $notification->push(_("Your time was successfully entered."), 'horde.success', array('sticky'));
                $GLOBALS['injector']->getInstance('Hermes_Driver')->enterTime($GLOBALS['registry']->getAuth(), $info);
            }
        } catch (Exception $e) {
            Horde::logMessage($e, 'err');
            $notification->push(sprintf(_("There was an error storing your timesheet: %s"), $e->getMessage()), 'horde.error');
            header('Location: ' . $url);
            exit;
        }
    }
    break;

default:
    if ($vars->exists('id')) {
        // We are updating a specific entry, load it into the form variables.
        $id = $vars->get('id');
        if (!Hermes::canEditTimeslice($id)) {
            $notification->push(_("Access denied; user cannot modify this timeslice."), 'horde.error');
            Horde::url('time.php')->redirect();
        }
        $myhours = $GLOBALS['injector']->getInstance('Hermes_Driver')->getHours(array('id' => $id));
        if (is_array($myhours)) {
            foreach ($myhours as $item) {
                if (isset($item['id']) && $item['id'] == $id) {
                    foreach ($item as $key => $value) {
                        $vars->set($key, $value);
                    }
                }
            }
        }
    }
    $form = new Hermes_Form_Time_Entry($vars);
    break;
}
$form->setCostObjects($vars);

$page_output->header(array(
    'title' => $vars->exists('id') ? _("Edit Time") : _("New Time")
));
$notification->notify(array('listeners' => 'status'));
$form->renderActive(new Horde_Form_Renderer(), $vars, Horde::url('entry.php'), 'post');
$page_output->footer();
