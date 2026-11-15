<?php

declare(strict_types=1);

/*
 * Entrada com provedores externos. Os botões são apenas visuais por enquanto:
 * a integração (OAuth) vem depois, então ficam desabilitados e avisados como
 * "em breve" para não prometer algo que ainda não funciona.
 */
?>
<div class="auth__social">
	<button class="auth__social-button" type="button" disabled aria-describedby="auth-social-note">
		<svg class="auth__social-icon" viewBox="0 0 48 48" aria-hidden="true">
			<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
			<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
			<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
			<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
		</svg>
		<span>Continuar com Google</span>
	</button>

	<button class="auth__social-button" type="button" disabled aria-describedby="auth-social-note">
		<svg class="auth__social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
			<path d="M16.36 1.43c0 1.14-.42 2.2-1.26 3.03-.9.9-2 .99-2.44.99-.06-1.2.42-2.4 1.2-3.2.86-.9 2.14-1.03 2.5-.82zM20.6 17.2c-.5 1.15-.74 1.66-1.38 2.68-.9 1.42-2.16 3.19-3.72 3.2-1.39.02-1.75-.9-3.63-.9-1.88 0-2.27.88-3.66.92-1.5.05-2.65-1.55-3.55-2.97-2.43-3.85-2.68-8.37-1.18-10.77 1.06-1.7 2.73-2.7 4.3-2.7 1.6 0 2.6.9 3.92.9 1.28 0 2.06-.9 3.9-.9 1.4 0 2.88.76 3.94 2.08-3.46 1.9-2.9 6.84.06 8.46z"/>
		</svg>
		<span>Continuar com Apple</span>
	</button>
</div>

<p class="auth__social-note" id="auth-social-note">Login social em breve. Por enquanto, use seu e-mail.</p>

<p class="auth__divider"><span>ou</span></p>
