<?php

/**
 * @file
 * Double elimination controller, new system.
 */

/**
 * A class defining how matches are created, and rendered for this style
 * tournament.
 */
class SpecialDoubleEliminationController extends SingleEliminationController {
  /**
   * Theme implementations specific to this plugin.
   */
  public static function theme($existing, $type, $theme, $path) {
    $parent_info = TourneyController::getPluginInfo(get_parent_class($this));
    return parent::theme($existing, $type, $theme, $parent_info['path']);
  }
  
  public function buildBrackets() {
    parent::buildBrackets();
    $this->data['brackets']['loser'] = $this->buildBracket(array('id' => 'loser'));
    $this->data['brackets']['champion'] = $this->buildBracket(array('id' => 'champion'));
  }
  
  public function buildMatches() {
    parent::buildMatches();
    $this->buildBottomMatches();
    $this->buildChampionMatches();
  }
  
  public function buildBottomMatches() {
    $match = &drupal_static('match', 0);
    $round = &drupal_static('round', 0);
    
    // Rounds is a certain number, 2, 4, 6, based on the contestants
    $num_rounds = (log($this->slots, 2) - 1) * 2;
    foreach (range(1, $num_rounds) as $round_num) {
      $this->data['rounds'][++$round] = 
        $this->buildRound(array('id' => $round_num, 'bracket' => 'loser'));
        
      // Bring the round number down to a unique number per group of two
      $round_group = ceil($round_num / 2);
      
      // Matches is a certain number based on the round number and slots
      // The pattern is powers of two, counting down: 8 8 4 4 2 2 1 1
      $num_matches = $this->slots / pow(2, $round_group + 1);
      foreach (range(1, $num_matches) as $roundMatch) {
        $this->data['matches'][++$match] = 
          $this->buildMatch(array(
            'id' => $match,
            'round' => $round_num,
            'roundMatch' => (int) $roundMatch,
            'bracket' => 'loser',
        ));
      }
    }
  }
  
  public function buildChampionMatches() {
    $match = &drupal_static('match', 0);
    $round = &drupal_static('round', 0);
    
    foreach (array(1, 2) as $round_num) {
      $this->data['rounds'][++$round] = 
        $this->buildRound(array('id' => $round_num, 'bracket' => 'champion'));
      
      $this->data['matches'][++$match] = 
        $this->buildMatch(array(
          'id' => $match,
          'round' => $round_num,
          'roundMatch' => 1,
          'bracket' => 'champion',
      ));
    }
  }
  
  /**
   * Find and populate next/previous match pathing on the matches data array for
   * each match.
   */
  public function populatePositions() {
    parent::populatePositions();
    $this->populateLoserPositions();
  }
  
  public function populateLoserPositions() {
    
  }
  
  public function render() {
    dpm($this->calculateNextPosition(2, 'loser'));
    return parent::render();
  }
  
  /**
    * Given a match place integer, returns the next match place based on
    * either 'winner' or 'loser' direction
    *
    * @param $place
    *   Match placement, zero-based. round 1 match 1's match placement is 0
    * @param $direction
    *   Either 'winner' or 'loser'
    * @return $place
    *   Match placement of the desired match, otherwise NULL
    */
  protected function calculateNextPosition($place, $direction = "winner") {
    // @todo find a better way to count matches
    $slots = $this->slots;
    // Set up our handy values
    $matches = $slots * 2 - 1;
    $top_matches = $slots - 1;
    $bottom_matches = $top_matches - 1;

    // Champion Bracket
    if ( $place >= $matches - 2 ) {
      // Last match goes nowhere
      if ( $place == $matches - 1 ) return NULL;
      return $place + 1;
    }
    
    if ( $direction == 'winner' ) {
     // Top Bracket
     if ( $place < $top_matches ) {
       // Last match in the top bracket goes to the champion bracket
       if ( $place == $top_matches - 1 ) return $matches - 2;
       return parent::calculateNextPosition($place);
     }
     // Bottom Bracket
     else {
       // Get out series to find out how to adjust our place
       $series = $this->magicSeries($top_matches - 1);
       return $place + $series[$place-$top_matches];
     }
    }
    elseif ( $direction == 'loser' ) {
      // Top Bracket
      if ( $place < $top_matches ) {
        if ( $place < $slots / 2 ) {
          $adj = 0;
          return parent::calculateNextPosition($place) + ($bottom_matches/2) + $adj;
        }
        // Otherwise, more magical math to determine placement
        $rev_round = floor(log($top_matches - $place, 2)) ;
        // Special adjustments come in on certain rounds of matches that
        // generally flips them around as such:
        //
        // 1, 2, 3, 4, 5, 6, 7, 8
        //          \/
        // 5, 6, 7, 8, 1, 2, 3, 4
        //
        // and on the special occasions with byes, it can go:
        //
        // 6, 5, 8, 7, 2, 1, 4, 3
        //
        if ( ( $rev_round - count($this->structure['bracket-top']['rounds']) ) % 2 == 0 ) {
          $round_matches = pow(2, $rev_round);
          $first_match = $top_matches - $round_matches * 2 + 1;
          $this_match = $place - $first_match;
          $half_matches = $round_matches / 2;
          $adj = 0;
          // Same special adjustment from the first round comes into play here in the second round
          if ( $place < $slots * 0.75 && !array_key_exists('matches', $bottom_bracket['rounds']['round-1']) ) {
            $adj = $this_match % 2 ? -1 : 1;
          }
          return $place + $top_matches - $round_matches + ( ( $this_match < $half_matches ) ? $half_matches : -$half_matches ) + $adj;
        }
        return $place + $top_matches - pow(2, floor(log($top_matches - $place, 2)));
      }
    }
    return NULL;
  }
  
  /**
   * This is a special function that I could have just stored as a fixed array,
   * but I wanted it to scale. It creates a special series of numbers that
   * affect where loser bracket matches go
   *
   * @param $until
   *   @todo I should change this to /2 to begin with, but for now it's the
   *   full number of bottom matches
   * @return $series
   *   Array of numbers
   */
  private function magicSeries($until) {
    $series = array();
    $i = 0;
    // We're working to 8 if until is 16, 4 if until is 8
    while ( $i < $until / 2 ) {
      // Add in this next double entry of numbers
      $series[] = ++$i;
      $series[] = $i;
      // If it's a power of two, throw in that many numbers extra
      if ( ($i & ($i - 1)) == 0 )
        foreach ( range(1, $i) as $n ) $series[] = $i;
    }
    // Remove the unnecessary last element in the series (which is the start
    // of the next iteration)
    while ( count($series) > $until )
      array_pop($series);
    // Reverse it so we work down
    return array_reverse($series);
  }
}
