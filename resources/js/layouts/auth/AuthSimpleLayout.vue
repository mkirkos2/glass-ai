<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="auth-liquid-page relative flex min-h-svh flex-col items-center justify-center overflow-hidden px-5 py-10 sm:px-8"
    >
        <!-- Ambient light effects -->
        <div class="auth-liquid-glow auth-liquid-glow-one"></div>
        <div class="auth-liquid-glow auth-liquid-glow-two"></div>
        <div class="auth-liquid-glow auth-liquid-glow-three"></div>

        <div class="auth-liquid-content relative z-10 w-full max-w-md">
            <div class="auth-liquid-card flex flex-col gap-8 p-6 sm:p-9">
                <div class="flex flex-col items-center gap-5">
                    <Link
                        :href="home()"
                        class="auth-liquid-logo flex items-center justify-center"
                        aria-label="Go to home"
                    >
                        <AppLogoIcon class="size-10 fill-current text-white" />
                    </Link>

                    <div class="space-y-2 text-center">
                        <h1
                            class="auth-liquid-title text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            {{ title }}
                        </h1>

                        <p
                            class="auth-liquid-description mx-auto max-w-xs text-sm leading-6"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>

                <slot />
            </div>

            <p class="auth-liquid-footer mt-6 text-center text-xs">
                Secure access to your account
            </p>
        </div>
    </div>
</template>

<style>
.auth-liquid-page {
    isolation: isolate;
    color: #f5f7ff;
    background:
        radial-gradient(
            ellipse at 50% -15%,
            rgba(87, 105, 190, 0.19),
            transparent 55%
        ),
        linear-gradient(145deg, #080a11 0%, #0d101a 48%, #080a10 100%);
}

.auth-liquid-glow {
    position: absolute;
    z-index: -1;
    pointer-events: none;
    border-radius: 9999px;
    filter: blur(75px);
    opacity: 0.42;
}

.auth-liquid-glow-one {
    top: 8%;
    left: -12%;
    width: 300px;
    height: 300px;
    background: rgba(75, 108, 255, 0.28);
}

.auth-liquid-glow-two {
    right: -10%;
    bottom: 5%;
    width: 320px;
    height: 320px;
    background: rgba(113, 71, 219, 0.22);
}

.auth-liquid-glow-three {
    top: 42%;
    left: 42%;
    width: 200px;
    height: 200px;
    background: rgba(67, 132, 190, 0.12);
}

.auth-liquid-card {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 28px;
    background:
        linear-gradient(
            145deg,
            rgba(255, 255, 255, 0.075),
            rgba(255, 255, 255, 0.018) 45%,
            rgba(255, 255, 255, 0.035)
        ),
        rgba(15, 18, 29, 0.76);
    box-shadow:
        0 32px 90px rgba(0, 0, 0, 0.38),
        inset 0 1px 0 rgba(255, 255, 255, 0.11),
        inset 0 -1px 0 rgba(255, 255, 255, 0.025);
    backdrop-filter: blur(30px) saturate(145%);
    -webkit-backdrop-filter: blur(30px) saturate(145%);
}

.auth-liquid-card::before {
    position: absolute;
    top: 0;
    right: 12%;
    left: 12%;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.34),
        transparent
    );
    content: '';
    pointer-events: none;
}

.auth-liquid-logo {
    width: 68px;
    height: 68px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 22px;
    background: linear-gradient(
        145deg,
        rgba(255, 255, 255, 0.16),
        rgba(255, 255, 255, 0.035)
    );
    box-shadow:
        0 10px 35px rgba(0, 0, 0, 0.22),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    transition:
        transform 220ms ease,
        border-color 220ms ease,
        background 220ms ease;
}

.auth-liquid-logo:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.12);
}

.auth-liquid-title {
    color: #f7f8ff;
    text-wrap: balance;
}

.auth-liquid-description {
    color: rgba(220, 225, 242, 0.62);
}

.auth-liquid-footer {
    color: rgba(220, 225, 242, 0.38);
}

/* Inputs */
.auth-liquid-card input:not([type='checkbox']):not([type='hidden']) {
    min-height: 46px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 13px;
    background: rgba(255, 255, 255, 0.045);
    color: #f7f8ff;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.12);
    transition:
        border-color 180ms ease,
        background 180ms ease,
        box-shadow 180ms ease;
}

.auth-liquid-card input:not([type='checkbox']):not([type='hidden']):hover {
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.06);
}

.auth-liquid-card input:not([type='checkbox']):not([type='hidden']):focus {
    border-color: rgba(139, 164, 255, 0.72);
    background: rgba(255, 255, 255, 0.065);
    box-shadow: 0 0 0 3px rgba(108, 137, 255, 0.13);
    outline: none;
}

.auth-liquid-card input::placeholder {
    color: rgba(220, 225, 242, 0.38);
}

/* Labels and links */
.auth-liquid-card label {
    color: rgba(239, 242, 255, 0.86);
}

.auth-liquid-card a:not(.auth-liquid-logo) {
    color: #b8c7ff;
    text-decoration-color: rgba(184, 199, 255, 0.4);
    transition:
        color 180ms ease,
        text-decoration-color 180ms ease;
}

.auth-liquid-card a:not(.auth-liquid-logo):hover {
    color: #e0e7ff;
    text-decoration-color: currentColor;
}

/* Primary button */
.auth-liquid-card button[type='submit'] {
    min-height: 47px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 14px;
    background: linear-gradient(135deg, #f5f7ff 0%, #cbd5ff 100%);
    color: #15182a;
    font-weight: 600;
    box-shadow:
        0 8px 24px rgba(83, 106, 220, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.75);
    transition:
        transform 180ms ease,
        filter 180ms ease,
        box-shadow 180ms ease;
}

.auth-liquid-card button[type='submit']:hover:not(:disabled) {
    transform: translateY(-1px);
    filter: brightness(1.04);
    box-shadow:
        0 12px 30px rgba(83, 106, 220, 0.28),
        inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.auth-liquid-card button[type='submit']:active:not(:disabled) {
    transform: translateY(0);
}

.auth-liquid-card button[type='submit']:disabled {
    cursor: wait;
    opacity: 0.65;
}

/* Remember-me checkbox */
.auth-liquid-card [role='checkbox'] {
    border-color: rgba(255, 255, 255, 0.28);
}

@media (max-width: 480px) {
    .auth-liquid-card {
        border-radius: 23px;
        backdrop-filter: blur(22px) saturate(135%);
        -webkit-backdrop-filter: blur(22px) saturate(135%);
    }

    .auth-liquid-logo {
        width: 60px;
        height: 60px;
        border-radius: 19px;
    }

    .auth-liquid-glow {
        filter: blur(55px);
        opacity: 0.32;
    }
}

@media (prefers-reduced-motion: reduce) {
    .auth-liquid-logo,
    .auth-liquid-card input,
    .auth-liquid-card a,
    .auth-liquid-card button {
        transition: none;
    }
}
</style>
