{{-- Funciones del lector de guías (va una vez en la página que usa el escáner) --}}
<script>
        /** Une varios objetos de Alpine conservando sus getters (el spread "..." los vuelve valores fijos) */
        function mezclar(...partes) {
            const r = {};
            partes.forEach(p => Object.defineProperties(r, Object.getOwnPropertyDescriptors(p)));
            return r;
        }

        /** Funciones del lector para mezclar en un componente Alpine: mezclar(lectorGuias(), ...) */
        function lectorGuias() {
            return {
                camara: false, aviso: '', avisoOk: true, _flujo: null, _ultimo: '', _hora: 0,
                avisar(texto, ok = true) {
                    this.aviso = texto; this.avisoOk = ok;
                    try { navigator.vibrate && navigator.vibrate(ok ? 60 : [80, 60, 80]); } catch (e) {}
                },
                async abrirCamara() {
                    try {
                        const detector = new BarcodeDetector({ formats: ['qr_code', 'code_128'] });
                        this._flujo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                        this.$refs.video.srcObject = this._flujo; await this.$refs.video.play(); this.camara = true;
                        const mirar = async () => {
                            if (!this.camara) return;
                            try {
                                for (const c of await detector.detect(this.$refs.video)) {
                                    // La misma etiqueta frente a la cámara no se cuenta dos veces seguidas
                                    if (c.rawValue !== this._ultimo || Date.now() - this._hora > 2500) { this._ultimo = c.rawValue; this._hora = Date.now(); this.leer(c.rawValue); }
                                }
                            } catch (e) {}
                            requestAnimationFrame(mirar);
                        };
                        mirar();
                    } catch (e) { this.avisar('No se pudo abrir la cámara. Revisa el permiso o usa el lector.', false); this.cerrarCamara(); }
                },
                cerrarCamara() { this.camara = false; (this._flujo?.getTracks() || []).forEach(t => t.stop()); this._flujo = null; },
                /** N° de guía y bulto de lo leído (la guía sola o el enlace del QR: …/rastreo/CODIGO?b=2) */
                guiaDe(texto) {
                    const m = String(texto).toUpperCase().match(/(?:^|\/)([A-HJ-NP-Z2-9]{10})(?:[?&#]B=(\d{1,3}))?(?:$|[?&#\/])/);
                    return m ? { codigo: m[1], bulto: m[2] ? Number(m[2]) : null } : null;
                },
            };
        }
    </script>
