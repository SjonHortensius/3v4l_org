/*
 * JS Linux main
 * 
 * Copyright (c) 2017 Fabrice Bellard
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
"use strict";

var term, console_write1;
var graphic_display, display_key_event, display_mouse_event;
var net_state, net_write_packet, net_set_carrier;
var display_wheel_event;
var fs_import_file;
var Module = {};
var downloading_timer_pending = false;
var downloading_timer;

function on_update_file(f)
{
    var f, reader;
    reader = new FileReader();
    reader.onload = function (ev) {
        var buf, buf_addr, buf_len;
        buf = new Uint8Array(reader.result);
        buf_len = buf.length;
        buf_addr = _malloc(buf_len);
        HEAPU8.set(buf, buf_addr);
        /* the buffer is freed by the function */
        fs_import_file(f.name, buf_addr, buf_len);
    };
    reader.readAsArrayBuffer(f);
}

function on_update_files(files)
{
    var i, n;
    n = files.length;
    for(i = 0; i < n; i++) {
        on_update_file(files[i]);
    }
}

function term_handler(str)
{
    var i;
    for(i = 0; i < str.length; i++) {
        console_write1(str.charCodeAt(i));
    }
}

function downloading_timer_cb()
{
    var el = document.getElementById("net_progress");
    el.style.visibility = "hidden";
    downloading_timer_pending = false;
}

function update_downloading(flag)
{
    var el;
    if (flag) {
        if (downloading_timer_pending) {
            clearTimeout(downloading_timer);
            downloading_timer_pending = false;
        } else {
            el = document.getElementById("net_progress");
            el.style.visibility = "visible";
        }
    } else {
        downloading_timer_pending = true;
        downloading_timer = setTimeout(downloading_timer_cb, 500);
    }
}

function get_params()
{
    var url, query_str, p, tab, i, params, tab2;
    query_str = window.location.href;
    p = query_str.indexOf("?");
    if (p < 0)
        return {};
    query_str = query_str.substr(p + 1);
    tab = query_str.split("&");
    params = {};
    for(i = 0; i < tab.length; i++) {
        tab2 = tab[i].split("=");
        params[decodeURIComponent(tab2[0])] = decodeURIComponent(tab2[1]);
    }
    return params;
}

function GraphicDisplay(parent_el, width, height)
{
    this.width = width;
    this.height = height;
    
    this.canvas_el = document.createElement("canvas");
    this.canvas_el.width = width; /* logical width */
    this.canvas_el.height = height; /* logical width */
    /* displayed size */
    this.canvas_el.style.width = width + "px";
    this.canvas_el.style.height = height + "px";
    this.canvas_el.style.cursor = "none";
    
    parent_el.appendChild(this.canvas_el);

    this.ctx = this.canvas_el.getContext("2d");
    /* clear the display */
    this.ctx.fillStyle = "#000000";
    this.ctx.fillRect(0, 0, width, height);
    
    this.image = this.ctx.createImageData(width, height);

    this.key_pressed = new Uint8Array(128);

    document.addEventListener("keydown",
                              this.keyDownHandler.bind(this), false);
    document.addEventListener("keyup", 
                              this.keyUpHandler.bind(this), false);
    document.addEventListener("blur", 
                              this.blurHandler.bind(this), false);

    this.canvas_el.onmousedown = this.mouseMoveHandler.bind(this);
    this.canvas_el.onmouseup = this.mouseMoveHandler.bind(this);
    this.canvas_el.onmousemove = this.mouseMoveHandler.bind(this);
    this.canvas_el.oncontextmenu = this.onContextMenuHandler.bind(this);
    this.canvas_el.onwheel = this.wheelHandler.bind(this);
}

GraphicDisplay.code_to_input_map = {
        "Escape": 0x01,
        "Digit1": 0x02,
        "Digit2": 0x03,
        "Digit3": 0x04,
        "Digit4": 0x05,
        "Digit5": 0x06,
        "Digit6": 0x07,
        "Digit7": 0x08,
        "Digit8": 0x09,
        "Digit9": 0x0a,
        "Digit0": 0x0b,
        "Minus": 0x0c,
        "Equal": 0x0d,
        "Backspace": 0x0e,
        "Tab": 0x0f,
        "KeyQ": 0x10,
        "KeyW": 0x11,
        "KeyE": 0x12,
        "KeyR": 0x13,
        "KeyT": 0x14,
        "KeyY": 0x15,
        "KeyU": 0x16,
        "KeyI": 0x17,
        "KeyO": 0x18,
        "KeyP": 0x19,
        "BracketLeft": 0x1a,
        "BracketRight": 0x1b,
        "Enter": 0x1c,
        "ControlLeft": 0x1d,
        "KeyA": 0x1e,
        "KeyS": 0x1f,
        "KeyD": 0x20,
        "KeyF": 0x21,
        "KeyG": 0x22,
        "KeyH": 0x23,
        "KeyJ": 0x24,
        "KeyK": 0x25,
        "KeyL": 0x26,
        "Semicolon": 0x27,
        "Quote": 0x28,
        "Backquote": 0x29,
        "ShiftLeft": 0x2a,
        "Backslash": 0x2b,
        "KeyZ": 0x2c,
        "KeyX": 0x2d,
        "KeyC": 0x2e,
        "KeyV": 0x2f,
        "KeyB": 0x30,
        "KeyN": 0x31,
        "KeyM": 0x32,
        "Comma": 0x33,
        "Period": 0x34,
        "Slash": 0x35,
        "ShiftRight": 0x36,
        "NumpadMultiply": 0x37,
        "AltLeft": 0x38,
        "Space": 0x39,
        "CapsLock": 0x3a,
        "F1": 0x3b,
        "F2": 0x3c,
        "F3": 0x3d,
        "F4": 0x3e,
        "F5": 0x3f,
        "F6": 0x40,
        "F7": 0x41,
        "F8": 0x42,
        "F9": 0x43,
        "F10": 0x44,
        "NumLock": 0x45,
        "ScrollLock": 0x46,
        "Numpad7": 0x47,
        "Numpad8": 0x48,
        "Numpad9": 0x49,
        "NumpadSubtract": 0x4a,
        "Numpad4": 0x4b,
        "Numpad5": 0x4c,
        "Numpad6": 0x4d,
        "NumpadAdd": 0x4e,
        "Numpad1": 0x4f,
        "Numpad2": 0x50,
        "Numpad3": 0x51,
        "Numpad0": 0x52,
        "NumpadDecimal": 0x53,
        "IntlBackslash": 0x56,
        "F11": 0x57,
        "F12": 0x58,

        "NumpadEnter": 96,
        "ControlRight": 97,
        "NumpadDivide": 98,
        "AltRight": 100,
        "Home": 102,
        "ArrowUp": 103,
        "PageUp": 104,
        "ArrowLeft": 105,
        "ArrowRight": 106,
        "End": 107,
        "ArrowDown": 108,
        "PageDown": 109,
        "Insert": 110,
        "Delete": 111,
        "OSLeft": 125,
        "OSRight": 126,
        "ContextMenu": 127,
};

GraphicDisplay.key_code_to_input_map = new Uint8Array([
    0, 0, 0, 0, 0, 0, 0, 0,
    0x0E, 0x0F, 0, 0, 0, 0x1C, 0, 0,
    0x2A, 0x1D, 0x38, 0, 0x3A, 0, 0, 0, /* 0x10 */
    0, 0, 0, 0x01, 0, 0, 0, 0,
    0x39, 104, 109, 107, 102, 105, 103, 106, /* 0x20 */
    0x50, 0, 0, 0, 0, 0x52, 0x53, 0,
    0x0B, 0x02, 0x03, 0x04,  0x05, 0x06, 0x07, 0x08, /* 0x30 */
    0x09, 0x0A, 0, 0x27, 0, 0x0D, 0, 0,
    0, 0x1E, 0x30, 0x2E, 0x20, 0x12, 0x21, 0x22, /* 0x40 */
    0x23, 0x17, 0x24, 0x25, 0x26, 0x32, 0x31, 0x18,
    0x19, 0x10, 0x13, 0x1F, 0x14, 0x16, 0x2F, 0x11, /* 0x50 */
    0x2D, 0x15, 0x2C, 125, 126, 127, 0, 0, 
    0x52, 0x4F, 0x50, 0x51, 0x4B, 0x4C, 0x4D, 0x47, /* 0x60 */
    0x48, 0x49, 0x37, 0x4e, 0, 0x4a, 0x53, 98,
    0x3B, 0x3C, 0x3D, 0x3E, 0x3F, 0x40, 0x41, 0x42, /* 0x70 */
    0x43, 0x44, 0x57, 0x58, 0, 0, 0, 0,
    0, 0, 0, 0, 0, 0, 0, 0, /* 0x80 */
    0, 0, 0, 0, 0, 0, 0, 0,
    0x45, 0, 0, 0, 0, 0, 0, 0, /* 0x90 */
    0, 0, 0, 0, 0, 0, 0, 0,
    0, 0, 0, 0, 0, 0, 0, 0, /* 0xa0 */
    0, 0, 0, 0, 0, 0x0C, 0, 0,
    0, 0, 0, 0, 0, 0, 0, 0, /* 0xb0 */
    0, 0, 0x27, 0x0D, 0x33, 0x0C, 0x34, 0x35,
    0x29, 0, 0, 0, 0, 0, 0, 0, /* 0xc0 */
    0, 0, 0, 0, 0, 0, 0, 0,
    0, 0, 0, 0, 0, 0, 0, 0, /* 0xd0 */
    0, 0, 0, 0x1A, 0x2B, 0x1B, 0x28, 0,
    125, 100, 0, 0, 0, 0, 0, 0, /* 0xe0 */
    0, 0, 0, 0, 0, 0, 0, 0,
]);

GraphicDisplay.prototype.keyHandler = function keyHandler(ev, isDown)
{
    var code, input_key_code;

    /* At least avoid exiting the navigator if Ctrl-Q or Ctrl-W are
     * pressed */
    if (ev.ctrlKey) {
        window.onbeforeunload = function() {
            window.onbeforeunload = null;
            return "CTRL-W or Ctrl-Q cannot be sent to the emulator.";
        };
    } else {
        window.onbeforeunload = null;
    }

    if (typeof ev.code != "undefined") {
        code = ev.code;
        input_key_code = GraphicDisplay.code_to_input_map[code];
        if (typeof input_key_code != "undefined") {
//            console.log("code=" + code + " isDown=" + isDown + " input_key_code=" + input_key_code);
            this.key_pressed[input_key_code] = isDown;
            display_key_event(isDown, input_key_code);

            if (ev.stopPropagation)
                ev.stopPropagation();
            if (ev.preventDefault)
                ev.preventDefault();
            return false;
        }
    } else {
        /* fallback using keyCodes. Works only with an US keyboard */
        code = ev.keyCode;
        if (code < 256) {
            input_key_code = GraphicDisplay.key_code_to_input_map[code];
//            console.log("keyCode=" + code + " isDown=" + isDown + " input_key_code=" + input_key_code);
            if (input_key_code) {
                this.key_pressed[input_key_code] = isDown;
                display_key_event(isDown, input_key_code);
                
                if (ev.stopPropagation)
                    ev.stopPropagation();
                if (ev.preventDefault)
                    ev.preventDefault();
                return false;
            }
        }
    }
    return true;
}

GraphicDisplay.prototype.keyDownHandler = function keyDownHandler(ev)
{
    return this.keyHandler(ev, 1);
}

GraphicDisplay.prototype.keyUpHandler = function keyUpHandler(ev)
{
    return this.keyHandler(ev, 0);
}

GraphicDisplay.prototype.blurHandler = function blurHandler(ev, isDown)
{
    var i, n, key_pressed;
    /* allow unloading the page */
    window.onbeforeunload = null;
    /* release all keys */
    key_pressed = this.key_pressed;
    for(i = 0; i < key_pressed.length; i++) {
        if (key_pressed[i]) {
            display_key_event(0, i);
            key_pressed[i] = 0;
        }
    }
}

GraphicDisplay.prototype.mouseMoveHandler = function (ev)
{
    var x, y, rect, buttons;
    rect = this.canvas_el.getBoundingClientRect();
    x = ev.clientX - rect.left;
    y = ev.clientY - rect.top;
    buttons = ev.buttons & 7;
//    console.log("mouse: x=" + x + " y=" + y + " buttons=" + buttons);
    display_mouse_event(x, y, buttons);
    if (ev.stopPropagation)
        ev.stopPropagation();
    if (ev.preventDefault)
        ev.preventDefault();
    return false;
}

GraphicDisplay.prototype.wheelHandler = function (ev)
{
    if (ev.deltaY < 0) {
        display_wheel_event(1);
    } else if (ev.deltaY > 0) {
        display_wheel_event(-1);
    }
    if (ev.stopPropagation)
        ev.stopPropagation();
    if (ev.preventDefault)
        ev.preventDefault();
}

/* disable contextual menu */
GraphicDisplay.prototype.onContextMenuHandler = function (ev)
{
    if (ev.stopPropagation)
        ev.stopPropagation();
    if (ev.preventDefault)
        ev.preventDefault();
    return false;
}

/* Network support */

function Ethernet(url)
{
    try {
        this.socket = new WebSocket(url);
    } catch(err) {
        this.socket = null;
        console.log("Could not open websocket url=" + url);
        return;
    }
    this.socket.binaryType = 'arraybuffer';
    this.socket.onmessage = this.messageHandler.bind(this);
    this.socket.onclose = this.closeHandler.bind(this);
    this.socket.onopen = this.openHandler.bind(this);
    this.socket.onerror = this.errorHandler.bind(this);
}

Ethernet.prototype.openHandler = function(e)
{
    net_set_carrier(1);
}

Ethernet.prototype.closeHandler = function(e)
{
    net_set_carrier(0);
}

Ethernet.prototype.errorHandler = function(e)
{
    console.log("Websocket error=" + e);
}

Ethernet.prototype.messageHandler = function(e)
{
    var str, buf_len, buf_addr, buf;
    if (e.data instanceof ArrayBuffer) {
        buf_len = e.data.byteLength;
        buf = new Uint8Array(e.data);
        buf_addr = _malloc(buf_len);
        HEAPU8.set(buf, buf_addr);
        net_write_packet(buf_addr, buf_len);
        _free(buf_addr);
    } else {
        str = e.data.toString();
        if (str.substring(0, 5) == "ping:") {
            try {
                this.socket.send('pong:' + str.substring(5));
            } catch (err) {
            }
        }
    }
}

Ethernet.prototype.recv_packet = function(buf)
{
    if (this.socket) {
        try {
            this.socket.send(buf);
        } catch (err) {
        }
    }
}

function start_vm(user, pwd)
{
    var url, mem_size, cpu, params, vm_url, cmdline, cols, rows, guest_url;
    var font_size, graphic_enable, width, height, net_url, alloc_size;
    var drive_url, vm_file;
    
    function loadScript(src, f) {
        var head = document.getElementsByTagName("head")[0];
        var script = document.createElement("script");
        script.src = src;
        var done = false;
        script.onload = script.onreadystatechange = function() { 
            // attach to both events for cross browser finish detection:
            if ( !done && (!this.readyState ||
                           this.readyState == "loaded" || this.readyState == "complete") ) {
                done = true;
                if (f) {
                    f();
                }
                script.onload = script.onreadystatechange = null;
                head.removeChild(script);
            }
        };
        head.appendChild(script);
    }

    function start()
    {
        /* C functions called from javascript */
        console_write1 = Module.cwrap('console_queue_char', null, ['number']);
        fs_import_file = Module.cwrap('fs_import_file', null, ['string', 'number', 'number']);
        display_key_event = Module.cwrap('display_key_event', null, ['number', 'number']);
        display_mouse_event = Module.cwrap('display_mouse_event', null, ['number', 'number', 'number']);
        display_wheel_event = Module.cwrap('display_wheel_event', null, ['number']);
        net_write_packet = Module.cwrap('net_write_packet', null, ['number', 'number']);
        net_set_carrier = Module.cwrap('net_set_carrier', null, ['number']);

        net_state = null;
        if (net_url != "") {
            net_state = new Ethernet(net_url);
        }

        Module.ccall("vm_start", null, ["string", "number", "string", "string", "number", "number", "number", "string"], [url, mem_size, cmdline, pwd, width, height, (net_state != null) | 0, drive_url]);
        pwd = null;
    }

    /* read the parameters */

    params = get_params();
    cpu = params["cpu"] || "x86";
    url = params["url"];
    mem_size = (params["mem"] | 0) || 128; /* in mb */
    cmdline = params["cmdline"] || "";
    cols = (params["cols"] | 0) || 80;
    rows = (params["rows"] | 0) || 30;
    font_size = (params["font_size"] | 0) || 15;
    guest_url = params["guest_url"] || "";
    width = (params["w"] | 0) || 1024;
    height = (params["h"] | 0) || 640;
    graphic_enable = params["graphic"] | 0;
    net_url = params["net_url"]; /* empty string means no network */
    if (typeof net_url == "undefined")
        net_url = "wss://relay.widgetry.org/";
    drive_url = params["drive_url"] || "";

    if (user) {
        cmdline += " LOGIN_USER=" + user;
    } else if (guest_url) {
        cmdline += " GUEST_URL=" + guest_url;
    }
    
    if (graphic_enable) {
        graphic_display = new GraphicDisplay(document.getElementById("term_container"), width, height);
    } else {
        width = 0;
        height = 0;
        /* start the terminal */
        term = new Term(cols, rows, term_handler, 10000);
        term.open(document.getElementById("term_container"),
                  document.getElementById("term_paste"));
        term.term_el.style.fontSize = font_size + "px";
        term.write("Loading...\r\n");
    }

//    console.log("cpu=" + cpu + " url=" + url + " mem=" + mem_size);

    switch(cpu) {
    case "x86":
        vm_file = "x86emu";
        break;
    case "riscv64":
    case "riscv":
        vm_file = "riscvemu64";
        break;
    case "riscv32":
        vm_file = "riscvemu32";
        break;
    default:
        term.writeln("Unknown cpu=" + cpu);
        return;
    }

    if (typeof WebAssembly === "object") {
        /* wasm support : the memory grows automatically */
        vm_url = vm_file + "-wasm.js";
    } else {
        /* set the total memory */
        alloc_size = mem_size;
        if (cpu == "x86")
            alloc_size += 16;
        if (graphic_enable) {
            /* frame buffer memory */
            alloc_size += (width * height * 4 + 1048576 - 1) >> 20;
        }
        alloc_size += 32; /* extra space (XXX: reduce it ?) */
        alloc_size = (alloc_size + 15) & -16; /* align to 16 MB */
        Module.TOTAL_MEMORY = alloc_size << 20;
        vm_url = vm_file + ".js";
    }
    Module.preRun = start;

    loadScript(vm_url, null);
}

function on_login()
{
    var login_wrap_el = document.getElementById("wrap");
    var term_wrap_el = document.getElementById("term_wrap");
    var form = document.getElementById("form");
    var status = document.getElementById("status");
    var user = form.user.value;
    var pwd = form.password.value;

    if (user.length <= 1) {
        status.innerHTML = "User name must be provided";
        return false;
    }
    
    login_wrap_el.style.display = "none";
    term_wrap_el.style.display = "block";
    form.password.value = "";
    form.user.value = "";
    
    start_vm(user, pwd);

    return false;
}

(function() {
    var login, params;

    params = get_params();
    login = params["login"] || 0;
    if (login) {
        var login_wrap_el = document.getElementById("wrap");
        login_wrap_el.style.display = "block";
    } else {
        var term_wrap_el = document.getElementById("term_wrap");
        term_wrap_el.style.display = "block";
        start_vm(null, null);
    }
})();
