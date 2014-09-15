<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2014 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class statsDAO extends baseDAO
{	
    /**
     * Stats page
     */
	function displayPage() 
	{
        // Render a Bandwidth Widget
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="stats"><input type="hidden" name="q" value="BandwidthGrid">');
        
        $formFields = array();
        $formFields[] = FormManager::AddText('fromdt', __('From Date'), date("Y-m-d", time() - (86400 * 35)), NULL, 'f');
        $formFields[] = FormManager::AddText('todt', __('To Date'), date("Y-m-d"), NULL, 't');

        // List of Displays this user has permission for
        $displays = $this->user->DisplayGroupList(1);
        array_unshift($displays, array('displayid' => 0, 'displaygroup' => 'All'));
        $formFields[] = FormManager::AddCombo(
            'displayid', 
            __('Display'), 
            NULL,
            $displays,
            'displayid',
            'displaygroup',
            NULL, 
            'd');

        Theme::Set('header_text', __('Bandwidth'));
        Theme::Set('form_fields', $formFields);
        Theme::Render('grid_render');

		// Proof of Play stats widget
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="stats"><input type="hidden" name="q" value="StatsGrid">');
        
        $formFields = array();
        $formFields[] = FormManager::AddText('fromdt', __('From Date'), date("Y-m-d", time() - 86400), NULL, 'f');
        $formFields[] = FormManager::AddText('todt', __('To Date'), date("Y-m-d"), NULL, 't');

        // List of Displays this user has permission for
        $displays = $this->user->DisplayGroupList(1);
        array_unshift($displays, array('displayid' => 0, 'displaygroup' => 'All'));
        $formFields[] = FormManager::AddCombo(
            'displayid', 
            __('Display'), 
            NULL,
            $displays,
            'displayid',
            'displaygroup',
            NULL, 
            'd');

        // List of Media this user has permission for
        $media = $this->user->MediaList();
        array_unshift($media, array('mediaid' => 0, 'media' => 'All'));
        $formFields[] = FormManager::AddCombo(
            'mediaid', 
            __('Media'), 
            NULL,
            $media,
            'mediaid',
            'media',
            NULL, 
            'm');

        // Call to render the template
        Theme::Set('header_text', __('Statistics'));
        Theme::Set('form_fields', $formFields);
        Theme::Render('grid_render');
	}

    /**
     * Shows the stats grid
     */
    public function StatsGrid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $fromDt = Kit::GetParam('fromdt', _POST, _STRING);
        $toDt = Kit::GetParam('todt', _POST, _STRING);
        $displayId = Kit::GetParam('displayid', _POST, _INT);
        $mediaId = Kit::GetParam('mediaid', _POST, _INT);

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt = date("Y-m-d", strtotime($toDt) + 86399);
        }

        Theme::Set('form_action', '');
        Theme::Set('form_meta', '<input type="hidden" name="p" value="stats"/><input type="hidden" name="q" value="OutputCSV"/><input type="hidden" name="displayid" value="' . $displayId . '" /><input type="hidden" name="fromdt" value="' . $fromDt . '" /><input type="hidden" name="todt" value="' . $toDt . '" />');
        
        // Get an array of display id this user has access to.
        $displays = $this->user->DisplayList();
        $display_ids = array();

        foreach($displays as $display) {
            $display_ids[] = $display['displayid'];
        }

        if (count($display_ids) <= 0)
            trigger_error(__('No displays with View permissions'), E_USER_ERROR);

        // 3 grids showing different stats.

        // Layouts Ran
        $SQL =  'SELECT display.Display, layout.Layout, COUNT(StatID) AS NumberPlays, SUM(TIME_TO_SEC(TIMEDIFF(end, start))) AS Duration, MIN(start) AS MinStart, MAX(end) AS MaxEnd ';
        $SQL .= '  FROM stat ';
        $SQL .= '  INNER JOIN layout ON layout.LayoutID = stat.LayoutID ';
        $SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
        $SQL .= " WHERE stat.type = 'layout' ";
        $SQL .= sprintf("  AND stat.end > '%s' ", $fromDt);
        $SQL .= sprintf("  AND stat.start <= '%s' ", $toDt);

        $SQL .= ' AND stat.displayID IN (' . implode(',', $display_ids) . ') ';

        if ($displayId != 0)
            $SQL .= sprintf("  AND stat.displayID = %d ", $displayId);

        $SQL .= 'GROUP BY display.Display, layout.Layout ';
        $SQL .= 'ORDER BY display.Display, layout.Layout';

        if (!$results = $this->db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get Layouts Shown'), E_USER_ERROR);
        }

        $cols = array(
                array('name' => 'Display', 'title' => __('Display')),
                array('name' => 'Layout', 'title' => __('Layout')),
                array('name' => 'NumberPlays', 'title' => __('Number of Plays')),
                array('name' => 'DurationSec', 'title' => __('Total Duration (s)')),
                array('name' => 'Duration', 'title' => __('Total Duration')),
                array('name' => 'MinStart', 'title' => __('First Shown')),
                array('name' => 'MaxEnd', 'title' => __('Last Shown'))
            );
        Theme::Set('table_cols', $cols);

        $rows = array();

        while ($row = $db->get_assoc_row($results))
        {
            $row['Display'] = Kit::ValidateParam($row['Display'], _STRING);
            $row['Layout'] = Kit::ValidateParam($row['Layout'], _STRING);
            $row['NumberPlays'] = Kit::ValidateParam($row['NumberPlays'], _INT);
            $row['DurationSec'] = Kit::ValidateParam($row['Duration'], _INT);
            $row['Duration'] = sec2hms(Kit::ValidateParam($row['Duration'], _INT));
            $row['MinStart'] = Kit::ValidateParam($row['MinStart'], _STRING);
            $row['MaxEnd'] = Kit::ValidateParam($row['MaxEnd'], _STRING);

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);
        Theme::Set('table_layouts_shown', Theme::RenderReturn('table_render'));

        // Media Ran
        $SQL =  'SELECT display.Display, media.Name, COUNT(StatID) AS NumberPlays, SUM(TIME_TO_SEC(TIMEDIFF(end, start))) AS Duration, MIN(start) AS MinStart, MAX(end) AS MaxEnd ';
        $SQL .= '  FROM stat ';
        $SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
        $SQL .= '  INNER JOIN  media ON media.MediaID = stat.MediaID ';
        $SQL .= " WHERE stat.type = 'media' ";
        $SQL .= sprintf("  AND stat.end > '%s' ", $fromDt);
        $SQL .= sprintf("  AND stat.start <= '%s' ", $toDt);
        $SQL .= ' AND stat.displayID IN (' . implode(',', $display_ids) . ') ';

        if ($mediaId != 0)
            $SQL .= sprintf("  AND media.MediaID = %d ", $mediaId);

        if ($displayId != 0)
            $SQL .= sprintf("  AND stat.displayID = %d ", $displayId);

        $SQL .= 'GROUP BY display.Display, media.Name ';
        $SQL .= 'ORDER BY display.Display, media.Name';

        if (!$results = $this->db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get Library Media Ran'), E_USER_ERROR);
        }

        $cols = array(
                array('name' => 'Display', 'title' => __('Display')),
                array('name' => 'Media', 'title' => __('Media')),
                array('name' => 'NumberPlays', 'title' => __('Number of Plays')),
                array('name' => 'DurationSec', 'title' => __('Total Duration (s)')),
                array('name' => 'Duration', 'title' => __('Total Duration')),
                array('name' => 'MinStart', 'title' => __('First Shown')),
                array('name' => 'MaxEnd', 'title' => __('Last Shown'))
            );
        Theme::Set('table_cols', $cols);
        $rows = array();

        while ($row = $db->get_assoc_row($results))
        {
            $row['Display'] = Kit::ValidateParam($row['Display'], _STRING);
            $row['Media'] = Kit::ValidateParam($row['Name'], _STRING);
            $row['NumberPlays'] = Kit::ValidateParam($row['NumberPlays'], _INT);
            $row['DurationSec'] = Kit::ValidateParam($row['Duration'], _INT);
            $row['Duration'] = sec2hms(Kit::ValidateParam($row['Duration'], _INT));
            $row['MinStart'] = Kit::ValidateParam($row['MinStart'], _STRING);
            $row['MaxEnd'] = Kit::ValidateParam($row['MaxEnd'], _STRING);

            $rows[] = $row;
        }
        Theme::Set('table_rows', $rows);
        Theme::Set('table_media_shown', Theme::RenderReturn('table_render'));

        // Media on Layouts Ran
        $SQL =  "SELECT display.Display, layout.Layout, IFNULL(media.Name, 'Text/Rss/Webpage') AS Name, COUNT(StatID) AS NumberPlays, SUM(TIME_TO_SEC(TIMEDIFF(end, start))) AS Duration, MIN(start) AS MinStart, MAX(end) AS MaxEnd ";
        $SQL .= '  FROM stat ';
        $SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
        $SQL .= '  INNER JOIN layout ON layout.LayoutID = stat.LayoutID ';
        $SQL .= '  LEFT OUTER JOIN media ON media.MediaID = stat.MediaID ';
        $SQL .= " WHERE stat.type = 'media' ";
        $SQL .= sprintf("  AND stat.end > '%s' ", $fromDt);
        $SQL .= sprintf("  AND stat.start <= '%s' ", $toDt);
        $SQL .= ' AND stat.displayID IN (' . implode(',', $display_ids) . ') ';

        if ($mediaId != 0)
            $SQL .= sprintf("  AND media.MediaID = %d ", $mediaId);

        if ($displayId != 0)
            $SQL .= sprintf("  AND stat.displayID = %d ", $displayId);

        $SQL .= "GROUP BY display.Display, layout.Layout, IFNULL(media.Name, 'Text/Rss/Webpage') ";
        $SQL .= "ORDER BY display.Display, layout.Layout, IFNULL(media.Name, 'Text/Rss/Webpage')";

        if (!$results = $this->db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get Library Media Ran'), E_USER_ERROR);
        }

        $cols = array(
                array('name' => 'Display', 'title' => __('Display')),
                array('name' => 'Layout', 'title' => __('Layout')),
                array('name' => 'Media', 'title' => __('Media')),
                array('name' => 'NumberPlays', 'title' => __('Number of Plays')),
                array('name' => 'DurationSec', 'title' => __('Total Duration (s)')),
                array('name' => 'Duration', 'title' => __('Total Duration')),
                array('name' => 'MinStart', 'title' => __('First Shown')),
                array('name' => 'MaxEnd', 'title' => __('Last Shown'))
            );
        Theme::Set('table_cols', $cols);

        $rows = array();

        while ($row = $db->get_assoc_row($results))
        {
            $row['Display'] = Kit::ValidateParam($row['Display'], _STRING);
            $row['Layout'] = Kit::ValidateParam($row['Layout'], _STRING);
            $row['Media'] = Kit::ValidateParam($row['Name'], _STRING);
            $row['NumberPlays'] = Kit::ValidateParam($row['NumberPlays'], _INT);
            $row['DurationSec'] = Kit::ValidateParam($row['Duration'], _INT);
            $row['Duration'] = sec2hms(Kit::ValidateParam($row['Duration'], _INT));
            $row['MinStart'] = Kit::ValidateParam($row['MinStart'], _STRING);
            $row['MaxEnd'] = Kit::ValidateParam($row['MaxEnd'], _STRING);

            $rows[] = $row;
        }
        Theme::Set('table_rows', $rows);

        Theme::Set('table_media_on_layouts_shown', Theme::RenderReturn('table_render'));

        $output = Theme::RenderReturn('stats_page_grid');

        $response->SetGridResponse($output);
        $response->Respond();
    }

    public function BandwidthGrid() {

        $fromDt = strtotime(Kit::GetParam('fromdt', _POST, _STRING));
        $toDt = strtotime(Kit::GetParam('todt', _POST, _STRING));

        // Get some data for a bandwidth chart
        try {
            $dbh = PDOConnect::init();
        
            $displayId = Kit::GetParam('displayid', _POST, _INT);
            $params = array(
                'month' => $fromDt,
                'month2' => $toDt
                );

            $SQL = 'SELECT display.display, IFNULL(SUM(Size), 0) AS size ';
            
            if ($displayId != 0)
                $SQL .= ', bandwidthtype.name AS type ';

            $SQL .= ' FROM `bandwidth`
                    INNER JOIN `display`
                    ON display.displayid = bandwidth.displayid';

            if ($displayId  != 0)
                $SQL .= '
                        INNER JOIN bandwidthtype
                        ON bandwidthtype.bandwidthtypeid = bandwidth.type
                    ';

            $SQL .= '  WHERE month > :month 
                    AND month < :month2 ';

            if ($displayId != 0) {
                $SQL .= ' AND display.displayid = :displayid ';
                $params['displayid'] = $displayId;
            }

            $SQL .= 'GROUP BY display.display ';

            if ($displayId != 0)
                $SQL .= ' , bandwidthtype.name ';

            $SQL .= 'ORDER BY display.display';

            Debug::LogEntry('audit', $SQL . '. Params = ' . var_export($params, true), get_class(), __FUNCTION__);

            $sth = $dbh->prepare($SQL);

            $sth->execute($params);

            // Get the results
            $results = $sth->fetchAll();

            $maxSize = 0;
            foreach ($results as $library) {
                $maxSize = ($library['size'] > $maxSize) ? $library['size'] : $maxSize;
            }

            // Decide what our units are going to be, based on the size
            $base = floor(log($maxSize) / log(1024));

            $output = array();

            foreach ($results as $row) {

                // label depends whether we are filtered by display
                if ($displayId != 0) {
                    $label = $row['type'];
                }
                else {
                    $label = $row['display'];
                }

                $output[] = array(
                        'label' => $label, 
                        'value' => round((double)$row['size'] / (pow(1024, $base)), 2)
                    );
            }

            // Set the data
            Theme::Set('bandwidthWidget', json_encode($output));

            // Set up some suffixes
            $suffixes = array('bytes', 'k', 'M', 'G', 'T');    
            Theme::Set('bandwidthWidgetUnits', $suffixes[$base]);
            
            $output = Theme::RenderReturn('stats_page_bandwidth');

            $response = new ResponseManager();
            $response->SetGridResponse($output);
            $response->Respond();
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            // Show the error in place of the bandwidth chart
            Theme::Set('widget-error', 'Unable to get widget details');
        }
    }
	
	/**
	 * Outputs a CSV of stats
	 * @return 
	 */
	public function OutputCSV()
	{
		$db 		=& $this->db;
		$output		= '';
		
		// We are expecting some parameters
		$fromdt		= Kit::GetParam('fromdt', _GET, _STRING);
		$todt		= Kit::GetParam('todt', _GET, _STRING);
		$displayID	= Kit::GetParam('displayid', _GET, _INT);

        if ($fromdt == $todt) {
            $todt = date("Y-m-d", strtotime($todt) + 86399);
        }

		// We want to output a load of stuff to the browser as a text file.
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="stats.csv"');
		header("Content-Transfer-Encoding: binary");
		header('Accept-Ranges: bytes');
		
        // Get an array of display id this user has access to.
        $displays = $this->user->DisplayList();
        $display_ids = array();

        foreach($displays as $display) {
            $display_ids[] = $display['displayid'];
        }

        if (count($display_ids) <= 0) {
            echo __('No displays with View permissions');
            exit;
        }
        
		$SQL =  'SELECT stat.*, display.Display, layout.Layout, media.Name AS MediaName ';
		$SQL .= '  FROM stat ';
		$SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
		$SQL .= '  INNER JOIN layout ON layout.LayoutID = stat.LayoutID ';
		$SQL .= '  LEFT OUTER JOIN media ON media.mediaID = stat.mediaID ';
		$SQL .= ' WHERE 1=1 ';
		$SQL .= sprintf("  AND stat.end > '%s' ", $fromdt);
		$SQL .= sprintf("  AND stat.start <= '%s' ", $todt);

        $SQL .= ' AND stat.displayID IN (' . implode(',', $display_ids) . ') ';

		if ($displayID != 0)
		{
			$SQL .= sprintf("  AND stat.displayID = %d ", $displayID);
		}

        $SQL .= " ORDER BY stat.start ";
		
		Debug::LogEntry('audit', $SQL, 'Stats', 'OutputCSV');
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error('Failed to query for Stats.', E_USER_ERROR);
		}
		
		// Header row
		$output		.= "Type, FromDT, ToDT, Layout, Display, Media, Tag\n";
		
		while($row = $db->get_assoc_row($result))
		{
			// Read the columns
			$type		= Kit::ValidateParam($row['Type'], _STRING);
			$fromdt		= Kit::ValidateParam($row['start'], _STRING);
			$todt		= Kit::ValidateParam($row['end'], _STRING);
			$layout		= Kit::ValidateParam($row['Layout'], _STRING);
			$display	= Kit::ValidateParam($row['Display'], _STRING);
			$media		= Kit::ValidateParam($row['MediaName'], _STRING);
			$tag		= Kit::ValidateParam($row['Tag'], _STRING);
			
			$output		.= "$type, $fromdt, $todt, $layout, $display, $media, $tag\n";
		}
		
		//Debug::LogEntry('audit', 'Output: ' . $output, 'Stats', 'OutputCSV');
		
		echo $output;
		exit;
	}
}
?>