<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Challenger extends Model
{
	protected $guarded = ['id'];

	protected $dates = [ 
		'created_at', 'updated_at', 'join_date'
	];

	protected $dedupe_relations = [
		'data', 'social', 'badges'
	];

	public function getDedupeRelations()
	{
		return $this->dedupe_relations;
	}

	protected static function boot()
	{
		parent::boot();
	
		static::addGlobalScope('exclude_merged', function (\Illuminate\Database\Eloquent\Builder $builder) {
			$builder->whereNull('merged_into_challenger_id');
		});
	}

	function type() {
		return $this->belongsTo('App\Models\Type');
	}

	function joined_season() {
		return $this->belongsTo('App\Models\Season');
	}

	function data() {
		return $this->hasMany('App\Models\ChallengerData');
	}

	function social() {
		return $this->hasMany('App\Models\ChallengerSocial');
	}

	function seasons()
	{
		return $this->belongsToMany('App\Models\Season', 'challenger_season')->withTimestamps();
	}

	function currentSeason()
	{
		return $this->seasons()->where('is_current', true);
	}

	function badges() {
		return $this->belongsToMany('App\Models\Badge', 'challenger_badge')
			->orderBy('challenger_badge.awarded_at')
			->withPivot('type_id', 'id');
	}

	function getCurrentSeasonBadgesAttribute($value) {
		if (is_null($value)) {
			$value = $this->current_season_badges = $this->seasonBadgeCount();
			$this->save();
		}

		return $value;
	}

	function getCurrentSeasonTypePointsAttribute($value) {
		if (is_null($value)) {
			$value = $this->current_season_type_points = $this->seasonTypePointCount();
			$this->save();
		}

		return $value;
	}

	public function newPivot(Model $parent, array $attributes, $table, $exists, $using = NULL) {
		if ($parent instanceof Badge) {
			return new ChallengerBadgePivot($parent, $attributes, $table, $exists, $using);
		}

		return parent::newPivot($parent, $attributes, $table, $exists);
	}

	function seasonBadges($season = null, $gym_point = null) {
		if (is_null($season)) {
			$id = Season::currentSeason()->id;
		} elseif (is_a($season, Season::class)) {
			$id = $season->id;
		} else {
			$id = $season;
		}

		$badges = $this->badges()->where('season_id', $id);

		if (!is_null($gym_point) && $this->type_id) {
			if ($gym_point) {
				$badges = $badges->wherePivot('type_id', $this->type_id);
			} else {
				$badges = $badges->wherePivot('type_id', '!=', $this->type_id);
			}
		}
		
		return $badges->get();
	}

	function eligibleBadges($season = null) {
		if (is_null($season)) {
			$season = Season::currentSeason();
		} elseif (is_a($season, Season::class)) {
			// do nothing
		} else {
			$season = $season->find($season);
		}

		$active_badges = $this->seasonBadges($season, true);
		return $season->badges()->whereNotIn('id', $active_badges->pluck('id'))->get();
	}

	function seasonBadgeCount() {
		return $this->badges()->where('season_id', Season::currentSeason()->id)->count();
	}

	function seasonTypePointCount() {
		return $this->badges()->where('season_id', Season::currentSeason()->id)->where('challenger_badge.type_id', $this->type_id)->count();
	}

	function __toString() {
		return $this->name;
	}

	function isRegistered($season = null)
	{
		if (is_null($season)) {
			$season = Season::currentSeason();
		}
		$this->load('seasons');
		return $this->seasons->contains($season);
	}

	function registerForSeason($season = null) {
		if (is_null($season)) {
			$season = Season::currentSeason();
		}

		if (!$this->isRegistered($season)) {
			$this->seasons()->attach($season);
		}
	}

	function awardBadge(Badge $badge, $type_id = null) {
		if ($type_id != $this->type_id) {
			$type_id = null;
		}

		$found_badge = $this->badges->find($badge);

		if (is_null($found_badge)) {
			$this->badges()->attach([
				$badge->id => [
					'awarded_by_id' => \Auth::user()->id,
					'awarded_at' => \Carbon\Carbon::now(),
					'type_id' => $type_id
				]
			]);
		} else if (!is_null($type_id)) {
			$found_badge->pivot->type_id = $type_id;
			$found_badge->pivot->save();
		}

		$this->registerForSeason($badge->season);

		$this->current_season_badges = $this->seasonBadgeCount();
		$this->current_season_type_points = $this->seasonTypePointCount();
		$this->save();
	}

	function removeBadge(Badge $badge) {
		if ($this->badges()->exists($badge->id)) {
			$result = $this->badges()->detach($badge->id);
			$this->current_season_badges = $this->seasonBadgeCount();
			$this->save();
		} else {
			return true;
		}
		return $result;
	}

	function syncBadges($badges, $detach = true) {
		$sync = $this->badges()->sync($badges, $detach);
		$this->current_season_badges = $this->seasonBadgeCount();
		$this->save();
		return $sync;
	}
}
