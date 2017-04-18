/*
 diff v2.0.1

Software License Agreement (BSD License)

Copyright (c) 2009-2015, Kevin Decker <kpdecker@gmail.com>

All rights reserved.

Redistribution and use of this software in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

 Redistributions of source code must retain the above
  copyright notice, this list of conditions and the
  following disclaimer.

 Redistributions in binary form must reproduce the above
  copyright notice, this list of conditions and the
  following disclaimer in the documentation and/or other
  materials provided with the distribution.

 Neither the name of Kevin Decker nor the names of its
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
/* Wrapped by Sjon Hortensius for 3v4l.org */

var JsDiff={};
(function(){function r(){}function v(a,b,c,f,k){for(var d=0,g=b.length,h=0,l=0;d<g;d++){var e=b[d];if(e.removed)e.value=a.join(f.slice(l,l+e.count)),l+=e.count,d&&b[d-1].added&&(e=b[d-1],b[d-1]=b[d],b[d]=e);else{if(!e.added&&k){var m=c.slice(h,h+e.count),m=m.map(function(a,b){var c=f[l+b];return c.length>a.length?c:a});e.value=a.join(m)}else e.value=a.join(c.slice(h,h+e.count));h+=e.count;e.added||(l+=e.count)}}c=b[g-1];1<g&&(c.added||c.removed)&&a.equals("",c.value)&&(b[g-2].value+=c.value,b.pop());return b}r.prototype={diff:function(a,b,c){function f(a){return d?(setTimeout(function(){d(void 0,a)},0),!0):a}function k(){for(var c=-1*e;c<=e;c+=2){var d;d=n[c-1];var k=n[c+1],p=(k?k.newPos:0)-c;d&&(n[c-1]=void 0);var m=d&&d.newPos+1<h,p=k&&0<=p&&p<l;if(m||p){!m||p&&d.newPos<k.newPos?(d={newPos:k.newPos,components:k.components.slice(0)},g.pushComponent(d.components,void 0,!0)):(d.newPos++,g.pushComponent(d.components,!0,void 0));p=g.extractCommon(d,b,a,c);if(d.newPos+1>=h&&p+1>=l)return f(v(g,d.components,b,a,g.useLongestToken));n[c]=d}else n[c]=void 0}e++}c=void 0===c?{}:c;var d=c.callback;"function"===typeof c&&(d=c,c={});this.options=c;var g=this;a=this.castInput(a);b=this.castInput(b);a=this.removeEmpty(this.tokenize(a));b=this.removeEmpty(this.tokenize(b));var h=b.length,l=a.length,e=1,m=h+l,n=[{newPos:-1,components:[]}];c=this.extractCommon(n[0],b,a,0);if(n[0].newPos+1>=h&&c+1>=l)return f([{value:this.join(b),count:b.length}]);if(d)(function w(){setTimeout(function(){if(e>m)return d();k()||w()},0)})();else for(;e<=m;)if(c=k())return c},pushComponent:function(a,b,c){var f=a[a.length-1];f&&f.added===b&&f.removed===c?a[a.length-1]={count:f.count+1,added:b,removed:c}:a.push({count:1,added:b,removed:c})},extractCommon:function(a,b,c,f){var k=b.length,d=c.length,g=a.newPos;f=g-f;for(var h=0;g+1<k&&f+1<d&&this.equals(b[g+1],c[f+1]);)g++,f++,h++;h&&a.components.push({count:h});a.newPos=g;return f},equals:function(a,b){return a===b||this.options.ignoreCase&&a.toLowerCase()===b.toLowerCase()},removeEmpty:function(a){for(var b=[],c=0;c<a.length;c++)a[c]&&b.push(a[c]);return b},castInput:function(a){return a},tokenize:function(a){return a.split("")},join:function(a){return a.join("")}};var t=/^[a-zA-Z\u{C0}-\u{FF}\u{D8}-\u{F6}\u{F8}-\u{2C6}\u{2C8}-\u{2D7}\u{2DE}-\u{2FF}\u{1E00}-\u{1EFF}]+$/u,u=/\S/,q=new r;q.equals=function(a,b){this.options.ignoreCase&&(a=a.toLowerCase(),b=b.toLowerCase());return a===b||this.options.ignoreWhitespace&&!u.test(a)&&!u.test(b)};q.tokenize=function(a){a=a.split(/(\s+|\b)/);for(var b=0;b<a.length-1;b++)!a[b+1]&&a[b+2]&&t.test(a[b])&&t.test(a[b+2])&&(a[b]+=a[b+2],a.splice(b+1,2),b--);return a};this.diffWordsWithSpace=function(a,b,c){return q.diff(a,b,c)}}).apply(JsDiff);
