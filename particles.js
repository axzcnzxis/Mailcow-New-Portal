/**
 * 高阶物理交互 Canvas 粒子系统 (particles.js)
 * 具备以下特性：
 * 1. 捕获阶段事件穿透与全覆盖 ({ capture: true })，无视右键手势/防拦截
 * 2. 360° 烟花散开式点击迸发效果 (Burst on mousedown/click)
 * 3. 随机布朗运动、自然重力/浮力漂移与随机游离环境粒子
 * 4. 高分屏 (Retina Display) devicePixelRatio HDPI 适配
 */
(function() {
  'use strict';

  var canvas, ctx;
  var particles = [];
  var dpr = window.devicePixelRatio || 1;
  var mouse = { x: -1000, y: -1000, lastX: -1000, lastY: -1000, isMoving: false };
  var lastAmbientTime = 0;

  // 橙红色系 + 纯白 + 透光暖白
  var PARTICLE_COLORS = [
    'rgba(236, 101, 43, ',   /* 品牌橙红 #EC652B */
    'rgba(255, 87, 34, ',    /* 炫目橙 #FF5722 */
    'rgba(230, 74, 25, ',    /* 浓郁红橙 #E64A19 */
    'rgba(255, 171, 145, ',  /* 柔光浅橙 */
    'rgba(255, 255, 255, ',  /* 纯白 */
    'rgba(255, 243, 224, '   /* 暖白透光 */
  ];

  function Particle(x, y, isBurst, customVx, customVy) {
    this.x = x;
    this.y = y;
    
    if (isBurst) {
      // 点击迸发：360度强劲辐射冲击
      var angle = Math.random() * Math.PI * 2;
      var speed = Math.random() * 5.5 + 2.0; // 强初速度
      this.vx = Math.cos(angle) * speed;
      this.vy = Math.sin(angle) * speed;
      this.decay = Math.random() * 0.02 + 0.015; // 爆发粒子衰减更快
      this.size = Math.random() * 3.5 + 1.2;
    } else {
      // 游走/跟随粒子：加入布朗微小惯性与初始推力
      var speedX = (Math.random() - 0.5) * 2.2;
      var speedY = (Math.random() - 0.5) * 2.2;
      this.vx = speedX + (customVx || 0) * 0.1;
      this.vy = speedY + (customVy || 0) * 0.1;
      this.decay = Math.random() * 0.012 + 0.005; // 存活更久 2-4 秒
      this.size = Math.random() * 3.0 + 1.0;
    }

    this.initialSize = this.size;
    this.life = 1.0;
    // 随机加速度，赋予物理布朗游走感
    this.ax = (Math.random() - 0.5) * 0.04;
    this.ay = (Math.random() - 0.5) * 0.04 - 0.01; // 稍微向上自然浮动
    this.colorPrefix = PARTICLE_COLORS[Math.floor(Math.random() * PARTICLE_COLORS.length)];
  }

  Particle.prototype.update = function() {
    // 速度更新 + 随机微弱加速度 (布朗物理运动)
    this.vx += this.ax + (Math.random() - 0.5) * 0.05;
    this.vy += this.ay + (Math.random() - 0.5) * 0.05;
    
    // 空气阻力
    this.vx *= 0.96;
    this.vy *= 0.96;

    this.x += this.vx;
    this.y += this.vy;

    this.life -= this.decay;
    this.size = this.initialSize * (this.life > 0 ? this.life : 0);
  };

  Particle.prototype.draw = function(context) {
    if (this.life <= 0) return;
    context.save();
    context.beginPath();
    context.arc(this.x * dpr, this.y * dpr, this.size * dpr, 0, Math.PI * 2);

    // 强发光光晕
    var currentOpacity = Math.max(0, this.life);
    context.shadowBlur = 10 * dpr;
    context.shadowColor = this.colorPrefix + (currentOpacity * 0.9) + ')';
    context.fillStyle = this.colorPrefix + (currentOpacity * 0.85) + ')';
    context.fill();
    context.restore();
  };

  function initCanvas() {
    canvas = document.getElementById('particles-canvas');
    if (!canvas) {
      canvas = document.createElement('canvas');
      canvas.id = 'particles-canvas';
      // 关键 CSS：穿透所有页面事件
      canvas.style.cssText = 'position:fixed; top:0; left:0; width:100vw; height:100vh; pointer-events:none; z-index:9999;';
      document.body.appendChild(canvas);
    }
    ctx = canvas.getContext('2d');
    resizeCanvas();

    // 捕获阶段绑定 window/document 监听器，穿透右键手势与插件拦截
    window.addEventListener('resize', resizeCanvas, { passive: true });
    
    window.addEventListener('pointermove', onPointerMove, { capture: true, passive: true });
    window.addEventListener('pointerdown', onPointerDown, { capture: true, passive: true });
    
    // 兼容 fallback
    window.addEventListener('mousemove', onPointerMove, { capture: true, passive: true });
    window.addEventListener('mousedown', onPointerDown, { capture: true, passive: true });

    loop(0);
  }

  function resizeCanvas() {
    if (!canvas) return;
    dpr = window.devicePixelRatio || 1;
    canvas.width = window.innerWidth * dpr;
    canvas.height = window.innerHeight * dpr;
  }

  function emitParticles(x, y, count, isBurst, vx, vy) {
    for (var i = 0; i < count; i++) {
      if (particles.length < 240) { // 高性能上限保护
        particles.push(new Particle(x, y, isBurst, vx, vy));
      }
    }
  }

  function onPointerMove(e) {
    var vx = e.clientX - mouse.lastX;
    var vy = e.clientY - mouse.lastY;
    var speed = Math.sqrt(vx * vx + vy * vy);

    mouse.x = e.clientX;
    mouse.y = e.clientY;
    
    // 忽略大跳变，只要移动就无差别持续生成粒子
    if (speed > 0.5 && speed < 300) {
      var count = Math.min(Math.floor(speed / 3) + 1, 4);
      emitParticles(mouse.x, mouse.y, count, false, vx, vy);
    }

    mouse.lastX = e.clientX;
    mouse.lastY = e.clientY;
  }

  function onPointerDown(e) {
    if (e.clientX !== undefined && e.clientY !== undefined) {
      // 点击瞬间：360度发射 20-30 个爆裂发光粒子
      emitParticles(e.clientX, e.clientY, 24, true);
    }
  }

  function loop(timestamp) {
    // 高分屏 Canvas 清屏
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // 环境随机游离粒子：即使鼠标不活动，也在网页随机位置轻微生成 1-2 个自然飘散粒子
    if (timestamp - lastAmbientTime > 450) {
      lastAmbientTime = timestamp;
      if (particles.length < 150) {
        var rx = Math.random() * window.innerWidth;
        var ry = Math.random() * window.innerHeight;
        emitParticles(rx, ry, 1, false);
      }
    }

    // 粒子物理更新与渲染
    for (var i = particles.length - 1; i >= 0; i--) {
      var p = particles[i];
      p.update();
      p.draw(ctx);

      if (p.life <= 0) {
        particles.splice(i, 1);
      }
    }

    requestAnimationFrame(loop);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCanvas);
  } else {
    initCanvas();
  }
})();
