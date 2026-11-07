<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>three.js + ShaderMaterial Glass Effect</title>
    <style>
      body { margin: 0; overflow: hidden; background: #111; user-select: none; }
    </style>
  </head>
  <body>
    <div id="gui"></div>
    <script type="module">
      import * as THREE from 'https://cdn.skypack.dev/three@0.136.0/build/three.module.js';
      import { OrbitControls } from 'https://cdn.skypack.dev/three@0.136.0/examples/jsm/controls/OrbitControls.js';
      import { RoundedBoxGeometry } from 'https://cdn.skypack.dev/three@0.136.0/examples/jsm/geometries/RoundedBoxGeometry.js';
      import { EffectComposer } from 'https://cdn.skypack.dev/three@0.136.0/examples/jsm/postprocessing/EffectComposer.js';
      import { RenderPass } from 'https://cdn.skypack.dev/three@0.136.0/examples/jsm/postprocessing/RenderPass.js';
      import { UnrealBloomPass } from 'https://cdn.skypack.dev/three@0.136.0/examples/jsm/postprocessing/UnrealBloomPass.js';
      import { ShaderPass } from 'https://cdn.skypack.dev/three@0.136.0/examples/jsm/postprocessing/ShaderPass.js';
      import { GUI } from 'https://cdn.skypack.dev/dat.gui';

      const scene = new THREE.Scene();
      scene.background = new THREE.Color(0x11151c);

      const camera = new THREE.PerspectiveCamera(70, window.innerWidth / window.innerHeight, 0.1, 100);
      camera.position.set(0, 2, 2);

      const renderer = new THREE.WebGLRenderer({ antialias: true });
      renderer.setSize(window.innerWidth, window.innerHeight);
      document.body.appendChild(renderer.domElement);

      const controls = new OrbitControls(camera, renderer.domElement);
      controls.enableDamping = true;
      controls.dampingFactor = 0.05;

      const orbitGroup = new THREE.Group();
      scene.add(orbitGroup);

      // Object settings
      const objectSettings = {
        sphereCount: 12,
        sphereRadius: 3.0,
        sphereSize: 1.0,
        pyramidCount: 0,
        pyramidRadius: 1.5,
        pyramidSize: 0.4
      };

      function randomOrbitPosition(radius) {
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.random() * Math.PI;
        const x = radius * Math.sin(phi) * Math.cos(theta);
        const y = radius * Math.cos(phi);
        const z = radius * Math.sin(phi) * Math.sin(theta);
        return new THREE.Vector3(x, y, z);
      }

      function updateObjects() {
        // Clear existing objects
        orbitGroup.children = [];

        // Add spheres
        for (let i = 0; i < objectSettings.sphereCount; i++) {
          const pos = randomOrbitPosition(objectSettings.sphereRadius);
          const sphere = new THREE.Mesh(
            new THREE.SphereGeometry(objectSettings.sphereSize, 32, 32),
            new THREE.MeshStandardMaterial({ color: 0xff4444 })
          );
          sphere.position.copy(pos);
          orbitGroup.add(sphere);
        }

        // Add pyramids
        for (let i = 0; i < objectSettings.pyramidCount; i++) {
          const pos = randomOrbitPosition(objectSettings.pyramidRadius);
          const pyramid = new THREE.Mesh(
            new THREE.TetrahedronGeometry(objectSettings.pyramidSize),
            new THREE.MeshStandardMaterial({ color: 0x44ff88, wireframe: true })
          );
          pyramid.position.copy(pos);
          orbitGroup.add(pyramid);
        }
      }

      // Initial object creation
      updateObjects();

      scene.add(new THREE.AmbientLight(0xffffff, 0.5));

      const dirLight = new THREE.DirectionalLight(0xffffff, 1);
      dirLight.position.set(5, 5, 5);
      scene.add(dirLight);

      const rt = new THREE.WebGLRenderTarget(window.innerWidth, window.innerHeight);

      // Enhanced Glass ShaderMaterial
      const glassUniforms = {
        uScene: { value: rt.texture },
        uTime: { value: 0 },
        uResolution: { value: new THREE.Vector2(window.innerWidth, window.innerHeight) },
        uRefraction: { value: 0.02 },
        uDispersion: { value: 0.02 },
        uFrost: { value: 5.0 },
        uLightIntensity: { value: 1.0 },
        uReflectionStrength: { value: 0.4 },
        uPrismStrength: { value: 0.6 },
        uLightPos: { value: dirLight.position },
        uThickness: { value: 0.2 },
        uOpacity: { value: 0.7 },
      };

      const glassMaterial = new THREE.ShaderMaterial({
        uniforms: glassUniforms,
        vertexShader: `
        varying vec2 vUv;
        varying vec3 vNormal;
        varying vec3 vViewDir;
        varying vec3 vWorldPos;
        void main() {
          vUv = uv;
          vNormal = normalize(normalMatrix * normal);
          vViewDir = normalize((modelViewMatrix * vec4(position, 1.0)).xyz);
          vWorldPos = (modelMatrix * vec4(position, 1.0)).xyz;
          gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
        }
      `,
        fragmentShader: `
        uniform sampler2D uScene;
        uniform float uTime;
        uniform float uRefraction;
        uniform float uDispersion;
        uniform float uFrost;
        uniform float uLightIntensity;
        uniform float uReflectionStrength;
        uniform float uPrismStrength;
        uniform vec3 uLightPos;
        uniform vec2 uResolution;
        uniform float uThickness;
        uniform float uOpacity;
        varying vec2 vUv;
        varying vec3 vNormal;
        varying vec3 vViewDir;
        varying vec3 vWorldPos;

        // Improved noise function for frost effect
        float random(vec2 st) {
          return fract(sin(dot(st, vec2(12.9898, 78.233))) * 43758.5453123);
        }

        // Perlin noise for smoother frost
        float noise(vec2 st) {
          vec2 i = floor(st);
          vec2 f = fract(st);
          vec2 u = f * f * (3.0 - 2.0 * f);
          return mix(
            mix(random(i + vec2(0.0, 0.0)), random(i + vec2(1.0, 0.0)), u.x),
            mix(random(i + vec2(0.0, 1.0)), random(i + vec2(10, 1.0)), u.x),
            u.y
          );
        }

        void main() {
          // Enhanced refraction with thickness
          vec2 offset = vNormal.xy * uRefraction * (1.0 + sin(uTime * 0.5) * 0.1);
          float thicknessFactor = uThickness * (1.0 - dot(vNormal, vViewDir));
          offset *= (1.0 + thicknessFactor);
          vec4 colR = texture2D(uScene, vUv + offset * (1.0 + uDispersion));
          vec4 colG = texture2D(uScene, vUv + offset);
          vec4 colB = texture2D(uScene, vUv - offset * uDispersion);
          vec3 refracted = vec3(colR.r, colG.g, colB.b);

          // Magnified reflection with dynamic sampling
          vec2 reflUV = vUv + vNormal.xy * uReflectionStrength * (1.0 - dot(vNormal, vViewDir)) * 1.5;
          vec4 reflection = texture2D(uScene, reflUV);
          vec3 finalColor = mix(refracted, reflection.rgb, uReflectionStrength);

          // Improved frost effect with Perlin noise
          vec3 frost = vec3(0.0);
          float blurSize = uFrost / uResolution.x;
          for (int x = -2; x <= 2; x++) {
            for (int y = -2; y <= 2; y++) {
              vec2 sampleUV = vUv + vec2(x, y) * blurSize;
              float noiseVal = noise(sampleUV * 5.0 + uTime * 0.02);
              frost += texture2D(uScene, sampleUV).rgb * (1.0 + noiseVal * 0.15);
            }
          }
          frost /= 25.0;
          finalColor = mix(finalColor, frost, 0.35) * uLightIntensity;

          // Enhanced Fresnel for magnified edge highlights
          float fresnel = pow(1.0 - abs(dot(vNormal, vViewDir)), 4.0);
          finalColor += fresnel * 0.3;

          // Enhanced prismatic effect with subtle rainbow varnish
          vec3 lightDir = normalize(uLightPos - vWorldPos);
          vec3 reflectDir = reflect(-lightDir, vNormal);
          float spec = pow(max(dot(vViewDir, reflectDir), 0.0), 48.0);
          vec3 prismColor = vec3(0.0);
          prismColor.r = spec * (1.0 + uDispersion * 0.15);
          prismColor.g = spec * (1.0 + uDispersion * 0.05);
          prismColor.b = spec * (1.0 - uDispersion * 0.15);
          finalColor += prismColor * uPrismStrength * (1.0 + sin(uTime * 0.3) * 0.25);

          gl_FragColor = vec4(finalColor, uOpacity);
        }
      `,
        transparent: true,
        side: THREE.DoubleSide
      });

      // Glass cube
      const roundedCube = new THREE.Mesh(
        new RoundedBoxGeometry(1, 1, 1, 5, 0.2),
        glassMaterial
      );
      scene.add(roundedCube);

      const composer = new EffectComposer(renderer);
      const renderPass = new RenderPass(scene, camera);
      composer.addPass(renderPass);

      const bloomPass = new UnrealBloomPass(
        new THREE.Vector2(window.innerWidth, window.innerHeight),
        0.78,
        0.3,
        0.3
      );
      composer.addPass(bloomPass);

      const scanLineShader = {
        uniforms: {
          "tDiffuse": { value: null },
          "time": { value: 0.0 },
          "opacity": { value: 0.05 }
        },
        vertexShader: `
                varying vec2 vUv;
                void main() {
                    vUv = uv;
                    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                }
            `,
        fragmentShader: `
                uniform sampler2D tDiffuse;
                uniform float time;
                uniform float opacity;
                varying vec2 vUv;

                void main() {
                    vec4 color = texture2D(tDiffuse, vUv);
                    float scanline = step(1.0, mod(gl_FragCoord.y, 4.0)) * opacity;
                    color.rgb -= scanline;
                    gl_FragColor = color;
                }
            `
      };

      const scanLinePass = new ShaderPass(scanLineShader);
      composer.addPass(scanLinePass);

      // GUI setup
      const gui = new GUI();
      const glassSettings = { opacity: 0.7 };
      const reposition = { reposition: updateObjects };

      // Sphere controls
      const sphereFolder = gui.addFolder('Spheres');
      sphereFolder.add(objectSettings, 'sphereCount', 0, 50, 1).name('Count').onChange(updateObjects);
      sphereFolder.add(objectSettings, 'sphereRadius', 1.0, 5.0, 0.1).name('Orbit Radius').onChange(updateObjects);
      sphereFolder.add(objectSettings, 'sphereSize', 0.1, 1.0, 0.01).name('Size').onChange(updateObjects);

      // Pyramid controls
      const pyramidFolder = gui.addFolder('Pyramids');
      pyramidFolder.add(objectSettings, 'pyramidCount', 0, 50, 1).name('Count').onChange(updateObjects);
      pyramidFolder.add(objectSettings, 'pyramidRadius', 1.0, 5.0, 0.1).name('Orbit Radius').onChange(updateObjects);
      pyramidFolder.add(objectSettings, 'pyramidSize', 0.1, 1.0, 0.01).name('Size').onChange(updateObjects);

      // Existing glass controls
      const shaderGlassEffectFolder = gui.addFolder('Shader Glass Effect');
      shaderGlassEffectFolder.add(glassUniforms.uRefraction, 'value', 0.0, 0.1).name('Refraction');
      shaderGlassEffectFolder.add(glassUniforms.uDispersion, 'value', 0.0, 0.1).name('Dispersion');
      shaderGlassEffectFolder.add(glassUniforms.uFrost, 'value', 0.0, 20.0).name('Frost');
      shaderGlassEffectFolder.add(glassUniforms.uLightIntensity, 'value', 0.0, 2.0).name('Light Intensity');
      shaderGlassEffectFolder.add(glassUniforms.uReflectionStrength, 'value', 0.0, 0.5).name('Reflection Strength');
      shaderGlassEffectFolder.add(glassUniforms.uPrismStrength, 'value', 0.0, 1.0).name('Prism Strength');
      shaderGlassEffectFolder.add(glassUniforms.uThickness, 'value', 0.0, 0.5).name('Glass Thickness');
      shaderGlassEffectFolder.add(glassSettings, 'opacity', 0.0, 1.0).onChange(value => {
        glassUniforms.uOpacity.value = value;
      });

      const bloomFolder = gui.addFolder('Bloom');
      bloomFolder.add(bloomPass, 'strength', 0.0, 1.0).name('Bloom Strength');
      bloomFolder.add(bloomPass, 'radius', 0.0, 1.0).name('Bloom Radius');
      bloomFolder.add(bloomPass, 'threshold', 0.0, 1.0).name('Bloom Threshold');
       
      gui.add(reposition, 'reposition').name('Random Position');
      gui.add({ Fullscreen: toggleFullscreen }, 'Fullscreen').name('Toggle Fullscreen');
      
      gui.close();

      renderer.domElement.addEventListener('dblclick', () => {
        updateObjects();
      });

      function toggleFullscreen() {
        if (!document.fullscreenElement) {
          document.body.requestFullscreen();
        } else {
          document.exitFullscreen();
        }
      }

      // Animate
      function animate(time) {
        requestAnimationFrame(animate);
        glassUniforms.uTime.value = time * 0.001;
        controls.update();

        roundedCube.rotation.y += 0.005;
        orbitGroup.rotation.y -= 0.0025;

        renderer.setRenderTarget(rt);
        renderer.render(scene, camera);
        renderer.setRenderTarget(null);

        composer.render();
      }

      animate();

      window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
        composer.setSize(window.innerWidth, window.innerHeight);
        rt.setSize(window.innerWidth, window.innerHeight);
        glassUniforms.uResolution.value.set(window.innerWidth, window.innerHeight);
      });
    </script>
  </body>
</html>