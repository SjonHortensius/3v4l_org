/*!

 diff v2.0.1

Software License Agreement (BSD License)

Copyright (c) 2009-2015, Kevin Decker <kpdecker@gmail.com>

All rights reserved.

Redistribution and use of this software in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above
  copyright notice, this list of conditions and the
  following disclaimer.

* Redistributions in binary form must reproduce the above
  copyright notice, this list of conditions and the
  following disclaimer in the documentation and/or other
  materials provided with the distribution.

* Neither the name of Kevin Decker nor the names of its
  contributors may be used to endorse or promote products
  derived from this software without specific prior
  written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER
IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT
OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
@license
*/
// Wrapped by Sjon Hortensius for 3v4l.org

var JsDiff = {};
(function() {
	"use strict";


	function Diff() {}

	Diff.prototype = {
		diff(oldString, newString, options = {}) {
			let callback = options.callback;
			if (typeof options === 'function') {
				callback = options;
				options = {};
			}
			this.options = options;

			let self = this;

			function done(value) {
				if (callback) {
					setTimeout(function() {
						callback(undefined, value);
					}, 0);
					return true;
				} else {
					return value;
				}
			}

			// Allow subclasses to massage the input prior to running
			oldString = this.castInput(oldString);
			newString = this.castInput(newString);

			oldString = this.removeEmpty(this.tokenize(oldString));
			newString = this.removeEmpty(this.tokenize(newString));

			let newLen = newString.length,
				oldLen = oldString.length;
			let editLength = 1;
			let maxEditLength = newLen + oldLen;
			let bestPath = [{
				newPos: -1,
				components: []
			}];

			// Seed editLength = 0, i.e. the content starts with the same values
			let oldPos = this.extractCommon(bestPath[0], newString, oldString, 0);
			if (bestPath[0].newPos + 1 >= newLen && oldPos + 1 >= oldLen) {
				// Identity per the equality and tokenizer
				return done([{
					value: this.join(newString),
					count: newString.length
				}]);
			}

			// Main worker method. checks all permutations of a given edit length for acceptance.
			function execEditLength() {
				for (let diagonalPath = -1 * editLength; diagonalPath <= editLength; diagonalPath += 2) {
					let basePath;
					let addPath = bestPath[diagonalPath - 1],
						removePath = bestPath[diagonalPath + 1],
						oldPos = (removePath ? removePath.newPos : 0) - diagonalPath;
					if (addPath) {
						// No one else is going to attempt to use this value, clear it
						bestPath[diagonalPath - 1] = undefined;
					}

					let canAdd = addPath && addPath.newPos + 1 < newLen,
						canRemove = removePath && 0 <= oldPos && oldPos < oldLen;
					if (!canAdd && !canRemove) {
						// If this path is a terminal then prune
						bestPath[diagonalPath] = undefined;
						continue;
					}

					// Select the diagonal that we want to branch from. We select the prior
					// path whose position in the new string is the farthest from the origin
					// and does not pass the bounds of the diff graph
					if (!canAdd || (canRemove && addPath.newPos < removePath.newPos)) {
						basePath = clonePath(removePath);
						self.pushComponent(basePath.components, undefined, true);
					} else {
						basePath = addPath; // No need to clone, we've pulled it from the list
						basePath.newPos++;
						self.pushComponent(basePath.components, true, undefined);
					}

					oldPos = self.extractCommon(basePath, newString, oldString, diagonalPath);

					// If we have hit the end of both strings, then we are done
					if (basePath.newPos + 1 >= newLen && oldPos + 1 >= oldLen) {
						return done(buildValues(self, basePath.components, newString, oldString, self.useLongestToken));
					} else {
						// Otherwise track this path as a potential candidate and continue.
						bestPath[diagonalPath] = basePath;
					}
				}

				editLength++;
			}

			// Performs the length of edit iteration. Is a bit fugly as this has to support the
			// sync and async mode which is never fun. Loops over execEditLength until a value
			// is produced.
			if (callback) {
				(function exec() {
					setTimeout(function() {
						// This should not happen, but we want to be safe.
						/* istanbul ignore next */
						if (editLength > maxEditLength) {
							return callback();
						}

						if (!execEditLength()) {
							exec();
						}
					}, 0);
				}());
			} else {
				while (editLength <= maxEditLength) {
					let ret = execEditLength();
					if (ret) {
						return ret;
					}
				}
			}
		},
		pushComponent(components, added, removed) {
			let last = components[components.length - 1];
			if (last && last.added === added && last.removed === removed) {
				// We need to clone here as the component clone operation is just
				// as shallow array clone
				components[components.length - 1] = {
					count: last.count + 1,
					added: added,
					removed: removed
				};
			} else {
				components.push({
					count: 1,
					added: added,
					removed: removed
				});
			}
		},
		extractCommon(basePath, newString, oldString, diagonalPath) {
			let newLen = newString.length,
				oldLen = oldString.length,
				newPos = basePath.newPos,
				oldPos = newPos - diagonalPath,

				commonCount = 0;
			while (newPos + 1 < newLen && oldPos + 1 < oldLen && this.equals(newString[newPos + 1], oldString[oldPos + 1])) {
				newPos++;
				oldPos++;
				commonCount++;
			}

			if (commonCount) {
				basePath.components.push({
					count: commonCount
				});
			}

			basePath.newPos = newPos;
			return oldPos;
		},

		equals(left, right) {
			return left === right ||
				(this.options.ignoreCase && left.toLowerCase() === right.toLowerCase());
		},
		removeEmpty(array) {
			let ret = [];
			for (let i = 0; i < array.length; i++) {
				if (array[i]) {
					ret.push(array[i]);
				}
			}
			return ret;
		},
		castInput(value) {
			return value;
		},
		tokenize(value) {
			return value.split('');
		},
		join(chars) {
			return chars.join('');
		}
	};

	function buildValues(diff, components, newString, oldString, useLongestToken) {
		let componentPos = 0,
			componentLen = components.length,
			newPos = 0,
			oldPos = 0;

		for (; componentPos < componentLen; componentPos++) {
			let component = components[componentPos];
			if (!component.removed) {
				if (!component.added && useLongestToken) {
					let value = newString.slice(newPos, newPos + component.count);
					value = value.map(function(value, i) {
						let oldValue = oldString[oldPos + i];
						return oldValue.length > value.length ? oldValue : value;
					});

					component.value = diff.join(value);
				} else {
					component.value = diff.join(newString.slice(newPos, newPos + component.count));
				}
				newPos += component.count;

				// Common case
				if (!component.added) {
					oldPos += component.count;
				}
			} else {
				component.value = diff.join(oldString.slice(oldPos, oldPos + component.count));
				oldPos += component.count;

				// Reverse add and remove so removes are output first to match common convention
				// The diffing algorithm is tied to add then remove output and this is the simplest
				// route to get the desired output with minimal overhead.
				if (componentPos && components[componentPos - 1].added) {
					let tmp = components[componentPos - 1];
					components[componentPos - 1] = components[componentPos];
					components[componentPos] = tmp;
				}
			}
		}

		// Special case handle for when one terminal is ignored. For this case we merge the
		// terminal into the prior string and drop the change.
		let lastComponent = components[componentLen - 1];
		if (componentLen > 1 &&
			(lastComponent.added || lastComponent.removed) &&
			diff.equals('', lastComponent.value)) {
			components[componentLen - 2].value += lastComponent.value;
			components.pop();
		}

		return components;
	}

	function clonePath(path) {
		return {
			newPos: path.newPos,
			components: path.components.slice(0)
		};
	}

	// Based on https://en.wikipedia.org/wiki/Latin_script_in_Unicode
	//
	// Ranges and exceptions:
	// Latin-1 Supplement, 0080â€“00FF
	//  - U+00D7  Ã— Multiplication sign
	//  - U+00F7  Ã· Division sign
	// Latin Extended-A, 0100â€“017F
	// Latin Extended-B, 0180â€“024F
	// IPA Extensions, 0250â€“02AF
	// Spacing Modifier Letters, 02B0â€“02FF
	//  - U+02C7  Ë‡ &#711;  Caron
	//  - U+02D8  Ë˜ &#728;  Breve
	//  - U+02D9  Ë™ &#729;  Dot Above
	//  - U+02DA  Ëš &#730;  Ring Above
	//  - U+02DB  Ë› &#731;  Ogonek
	//  - U+02DC  Ëœ &#732;  Small Tilde
	//  - U+02DD  Ë &#733;  Double Acute Accent
	// Latin Extended Additional, 1E00â€“1EFF
	const extendedWordChars = /^[a-zA-Z\u{C0}-\u{FF}\u{D8}-\u{F6}\u{F8}-\u{2C6}\u{2C8}-\u{2D7}\u{2DE}-\u{2FF}\u{1E00}-\u{1EFF}]+$/u;

	const reWhitespace = /\S/;

	var wordDiff = new Diff();
	wordDiff.equals = function(left, right) {
		if (this.options.ignoreCase) {
			left = left.toLowerCase();
			right = right.toLowerCase();
		}
		return left === right || (this.options.ignoreWhitespace && !reWhitespace.test(left) && !reWhitespace.test(right));
	};
	wordDiff.tokenize = function(value) {
		let tokens = value.split(/(\s+|\b)/);

		// Join the boundary splits that we do not consider to be boundaries. This is primarily the extended Latin character set.
		for (let i = 0; i < tokens.length - 1; i++) {
			// If we have an empty string in the next field and we have only word chars before and after, merge
			if (!tokens[i + 1] && tokens[i + 2] &&
				extendedWordChars.test(tokens[i]) &&
				extendedWordChars.test(tokens[i + 2])) {
				tokens[i] += tokens[i + 2];
				tokens.splice(i + 1, 2);
				i--;
			}
		}

		return tokens;
	};

	this.diffWordsWithSpace = function(oldStr, newStr, options) {
		return wordDiff.diff(oldStr, newStr, options);
	}
}).apply(JsDiff);
