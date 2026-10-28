/**
 * WebGL Smokey Background — Pesanvo Custom Shader
 */
(function () {
  const canvas = document.getElementById('auth-smokey-canvas');
  if (!canvas) return;

  const colorHex = canvas.dataset.color || '#2e7d32';

  const vertexSource = `
    attribute vec4 a_position;
    void main() {
      gl_Position = a_position;
    }
  `;

  const fragmentSource = `
    precision mediump float;
    uniform vec2 iResolution;
    uniform float iTime;
    uniform vec2 iMouse;
    uniform vec3 u_color;
    uniform float u_isDark;

    void mainImage(out vec4 fragColor, in vec2 fragCoord) {
      vec2 centeredUV = (2.0 * fragCoord - iResolution.xy) / min(iResolution.x, iResolution.y);
      float time = iTime * 0.5;
      vec2 mouse = iMouse / iResolution;
      vec2 rippleCenter = 2.0 * mouse - 1.0;
      vec2 distortion = centeredUV;
      for (float i = 1.0; i < 8.0; i++) {
        distortion.x += 0.5 / i * cos(i * 2.0 * distortion.y + time + rippleCenter.x * 3.1415);
        distortion.y += 0.5 / i * cos(i * 2.0 * distortion.x + time + rippleCenter.y * 3.1415);
      }
      float wave = abs(sin(distortion.x + distortion.y + time));
      float glow = smoothstep(0.9, 0.2, wave);
      
      // Elegant light vs dark background mix
      vec3 bgColorLight = vec3(250.0/255.0, 248.0/255.0, 245.0/255.0);
      vec3 bgColorDark = vec3(9.0/255.0, 9.0/255.0, 11.0/255.0);
      vec3 bgColor = mix(bgColorLight, bgColorDark, u_isDark);
      
      vec3 finalColor = mix(bgColor, u_color, glow * 0.35);
      fragColor = vec4(finalColor, 1.0);
    }

    void main() {
      mainImage(gl_FragColor, gl_FragCoord.xy);
    }
  `;

  function hexToRgb(hex) {
    const h = hex.replace('#', '');
    return [
      parseInt(h.substring(0, 2), 16) / 255,
      parseInt(h.substring(2, 4), 16) / 255,
      parseInt(h.substring(4, 6), 16) / 255,
    ];
  }

  const gl = canvas.getContext('webgl');
  if (!gl) return;

  function compileShader(type, source) {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
      console.error(gl.getShaderInfoLog(shader));
      gl.deleteShader(shader);
      return null;
    }
    return shader;
  }

  const vs = compileShader(gl.VERTEX_SHADER, vertexSource);
  const fs = compileShader(gl.FRAGMENT_SHADER, fragmentSource);
  if (!vs || !fs) return;

  const program = gl.createProgram();
  gl.attachShader(program, vs);
  gl.attachShader(program, fs);
  gl.linkProgram(program);
  if (!gl.getProgramParameter(program, gl.LINK_STATUS)) return;

  gl.useProgram(program);

  const buffer = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
  gl.bufferData(
    gl.ARRAY_BUFFER,
    new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]),
    gl.STATIC_DRAW
  );

  const posLoc = gl.getAttribLocation(program, 'a_position');
  gl.enableVertexAttribArray(posLoc);
  gl.vertexAttribPointer(posLoc, 2, gl.FLOAT, false, 0, 0);

  const iRes = gl.getUniformLocation(program, 'iResolution');
  const iTime = gl.getUniformLocation(program, 'iTime');
  const iMouse = gl.getUniformLocation(program, 'iMouse');
  const uColor = gl.getUniformLocation(program, 'u_color');
  const uIsDark = gl.getUniformLocation(program, 'u_isDark');

  const [r, g, b] = hexToRgb(colorHex);
  gl.uniform3f(uColor, r, g, b);

  let start = Date.now();
  let mouse = { x: 0, y: 0 };
  let hovering = false;

  function render() {
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width = w;
      canvas.height = h;
    }
    gl.viewport(0, 0, w, h);
    const t = (Date.now() - start) / 1000;
    gl.uniform2f(iRes, w, h);
    gl.uniform1f(iTime, t);
    gl.uniform2f(
      iMouse,
      hovering ? mouse.x : w / 2,
      hovering ? h - mouse.y : h / 2
    );
    
    const isDark = document.documentElement.classList.contains('dark') ? 1.0 : 0.0;
    gl.uniform1f(uIsDark, isDark);
    
    gl.drawArrays(gl.TRIANGLES, 0, 6);
    requestAnimationFrame(render);
  }

  canvas.addEventListener('mousemove', (e) => {
    const rect = canvas.getBoundingClientRect();
    mouse.x = e.clientX - rect.left;
    mouse.y = e.clientY - rect.top;
  });
  canvas.addEventListener('mouseenter', () => { hovering = true; });
  canvas.addEventListener('mouseleave', () => { hovering = false; });

  render();
})();
