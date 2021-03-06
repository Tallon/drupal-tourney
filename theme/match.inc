<?php

/*
 * @file
 * Holds the implementations for rendering the games table on the match edit 
 * page with the form buttons built in for the current game to choose a winner.
 * 
 */

/**
 * Gets a padded/trimmed list to throw into a table
 *
 * @param $ids
 *   List of game entity ids.
 * @param $total
 *   Total number of games in the match.
 * @return
 *   List of game entities, filled with null elements if less than total, or trimmed if more.
 */
function tourney_load_games($ids, $total) {
  $games = array();
  $game_list = tourney_game_load_multiple($ids);

  foreach ($game_list as $game) {
    $games[] = $game;
  }

  while (count($games) < $total) {
    $games[] = NULL;
  }

  while (count($games) > $total) {
    array_pop($games);
  }

  return $games;
}

/**
 * Renders a match to an HTML table
 *
 * @param $games
 *   List of game entities.
 * @return
 *   Rendered HTML table.
 */
function tourney_games_to_table($games) {
  $header = array('Team Name');
  $rows   = array();
  
  foreach ($games as $gn => $game) {
    $header[] = "Game " . ($gn + 1); //. ($game ? " | " . $game->id : "");
    
    // A game does not yet exist for this match.
    if ($game == NULL || !method_exists($game, 'getContestants') || count($game->getContestants()) < 1) {
      foreach ($rows as $k => $v) {
        $rows[$k][] = '-';
      }
    }
    else {
      foreach ($game->getContestants() as $id => $contestant) {
        // If the row doesn't already exist, create one.
        if (!isset($rows[$id])) {
          $rows[$id] = array($contestant->entity_name);
        }
        // Give the winner a W.
        if ($game->winner) {
          $rows[$id][] = $game->winner == $id ? 'W' : 'L';
        }
        // Render a small form in the row
        else {
          $form = drupal_get_form('tourney_game_win_form',
          array(
            'match' => $game->getMatch()->id, 
            'game' => $game->id, 
            'contestant' => $id, 
            'name' => $contestant->entity_name,
          ));
          
          $rows[$id][] = count($game->getContestants()) < 2 || $game->getMatch()->winner 
            ? '-' : drupal_render($form);
        }
      }
    }
  }
  $header[] = "Results";
  foreach ($rows as $k => $v) {
    $points = array_key_exists('W', $tally = array_count_values($v)) ? $tally['W'] : 0;
    $rows[$k][] = ($points > count($games) / 2) ? 'Winner' : '-';
  }
  return theme('table', array(
    'attributes' => array('id' => 'game-list'),
    'header' => $header,
    'rows' => $rows,
  ));
}

/**
 * Renders an HTML table of a match
 *
 * @param $vars
 *   Theme variables, needs only a match.
 * @return
 *   Rendered HTML table.
 */
function theme_tourney_render_match($vars) {
  $match = $vars['match'];
  $games = tourney_load_games($match->getGameIds(), $match->games);
  $table = tourney_games_to_table($games);
  return $table;
}
