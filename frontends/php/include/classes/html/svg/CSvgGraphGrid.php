<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CSvgGraphGrid extends CSvgTag {

	protected $points_value = [];
	protected $points_time = [];

	protected $position_x = 0;
	protected $position_y = 0;

	protected $width = 0;
	protected $height = 0;

	public function __construct(array $points_value = [], array $points_time = []) {
		parent::__construct('g', true);

		$this->addClass(CSvgTag::ZBX_STYLE_SVG_GRAPH_GRID);

		$this->points_value = $points_value;
		$this->points_time = $points_time;
	}

	public function getStyles() {
		return [
			'.'.CSvgTag::ZBX_STYLE_SVG_GRAPH_GRID.' path' => [
				'stroke-dasharray' => '2,2',
				'stroke' => 'rgba(120,120,120,.5)'
			]
		];
	}

	public function setPosition($x, $y) {
		$this->position_x = $x;
		$this->position_y = $y;

		return $this;
	}

	public function setSize($width, $height) {
		$this->width = $width;
		$this->height = $height;

		return $this;
	}

	public function toString($destroy = true) {
		parent::addItem($this->draw());

		return parent::toString($destroy);
	}

	protected function draw() {
		$path = (new CSvgPath());

		foreach ($this->points_time as $pos => $time) {
			if (($pos + $this->position_x) <= ($this->position_x + $this->width)) {
				$path
					->moveTo($pos + $this->position_x, $this->position_y)
					->lineTo($pos + $this->position_x, $this->position_y + $this->height);
			}
		};

		foreach ($this->points_value as $pos => $value) {
			if (($this->position_y + $this->height - $pos) <= ($this->position_y + $this->height)) {
				$path
					->moveTo($this->position_x, $this->position_y + $this->height - $pos)
					->lineTo($this->position_x + $this->width, $this->position_y + $this->height - $pos);
			}
		};

		return $path;
	}
}
