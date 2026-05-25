<?php

function onboarding_layout_styles(): void
{
    ?>
<style>
html.onboarding-page,
html.onboarding-page body {
    height: 100%;
    overflow: hidden;
}

html.onboarding-page #main-content.onboarding-root {
    height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.onboarding-shell {
    flex: 1;
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: none;
    margin: 0;
    min-height: 0;
    height: 100%;
    padding: 0;
    overflow: hidden;
}

.onboarding-split {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr;
    gap: 0;
    align-items: stretch;
    min-height: 0;
    overflow: hidden;
}

@media (min-width: 1024px) {
    .onboarding-split {
        grid-template-columns: 1fr 1fr;
    }
}

.onboarding-illustration-panel {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 0;
    padding: 1.5rem 1.25rem;
    border-radius: 0;
    background:
        radial-gradient(circle at 18% 22%, rgba(59, 130, 246, 0.1) 0%, transparent 42%),
        radial-gradient(circle at 82% 78%, rgba(147, 197, 253, 0.12) 0%, transparent 40%),
        radial-gradient(circle at 55% 50%, rgba(219, 234, 254, 0.2) 0%, transparent 55%),
        linear-gradient(145deg, #ffffff 0%, #ffffff 50%, #f8fafc 78%, #eff6ff 100%);
    border: none;
    border-bottom: 1px solid rgba(59, 130, 246, 0.1);
    overflow: hidden;
}

.onboarding-illustration-panel::before,
.onboarding-illustration-panel::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
}

.onboarding-illustration-panel::before {
    width: 22rem;
    height: 22rem;
    top: -4rem;
    left: -3rem;
    background: rgba(96, 165, 250, 0.14);
    filter: blur(70px);
}

.onboarding-illustration-panel::after {
    width: 18rem;
    height: 18rem;
    right: -2rem;
    bottom: -3rem;
    background: rgba(191, 219, 254, 0.18);
    filter: blur(65px);
}

@media (min-width: 1024px) {
    .onboarding-illustration-panel {
        padding: 2rem 2.5rem;
        border-bottom: none;
        border-right: 1px solid rgba(59, 130, 246, 0.1);
    }
}

.dark .onboarding-illustration-panel {
    background:
        radial-gradient(circle at 18% 22%, rgba(59, 130, 246, 0.28) 0%, transparent 42%),
        radial-gradient(circle at 82% 78%, rgba(96, 165, 250, 0.18) 0%, transparent 40%),
        radial-gradient(circle at 55% 50%, rgba(30, 64, 175, 0.2) 0%, transparent 55%),
        linear-gradient(145deg, #0f172a 0%, #1e3a5f 55%, #1e40af 100%);
    border-color: rgba(96, 165, 250, 0.22);
}

.dark .onboarding-illustration-panel::before {
    background: rgba(37, 99, 235, 0.35);
}

.dark .onboarding-illustration-panel::after {
    background: rgba(59, 130, 246, 0.28);
}

.onboarding-illustration-stack {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    min-height: 0;
    gap: 0;
}

.onboarding-illustration-inner {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    flex: 1 1 auto;
    min-height: 0;
}

.onboarding-tips {
    display: none;
    width: 100%;
    max-width: min(98%, 42rem);
    flex-shrink: 0;
    margin-top: 0.5rem;
}

@media (min-width: 1024px) {
    .onboarding-illustration-panel {
        flex-direction: column;
        justify-content: center;
        gap: 0.75rem;
    }

    .onboarding-tips {
        display: block;
        max-width: min(100%, 44rem);
        margin-top: 0;
        padding-top: 0;
    }
}

.onboarding-tips__card {
    position: relative;
    padding: 0.5rem 0.875rem 0.375rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(59, 130, 246, 0.14);
    background: rgba(255, 255, 255, 0.72);
    backdrop-filter: blur(10px);
    box-shadow:
        0 4px 24px rgba(37, 99, 235, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.85);
}

.dark .onboarding-tips__card {
    border-color: rgba(96, 165, 250, 0.22);
    background: rgba(15, 23, 42, 0.55);
    box-shadow:
        0 8px 32px rgba(0, 0, 0, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.06);
}

.onboarding-tips__viewport {
    display: grid;
    min-height: 2rem;
}

.onboarding-tips__slide {
    grid-area: 1 / 1;
    opacity: 0;
    visibility: hidden;
    transform: translateY(4px);
    transition:
        opacity 0.45s ease,
        transform 0.45s ease,
        visibility 0.45s;
    pointer-events: none;
}

.onboarding-tips__slide.is-active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    pointer-events: auto;
}

.onboarding-tips__slide.is-leaving {
    opacity: 0;
    transform: translateY(-3px);
}

.onboarding-tips__main {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.onboarding-tips__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    flex: none;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    background: rgba(59, 130, 246, 0.12);
    color: rgb(37, 99, 235);
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    white-space: nowrap;
}

.dark .onboarding-tips__badge {
    color: rgb(147, 197, 253);
    background: rgba(59, 130, 246, 0.22);
}

.onboarding-tips__badge svg {
    width: 0.75rem;
    height: 0.75rem;
    flex-shrink: 0;
}

.onboarding-tips__line {
    margin: 0;
    flex: 1 1 auto;
    min-width: 0;
    font-size: 0.75rem;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

.onboarding-tips__title {
    margin: 0;
    display: inline;
    font-size: inherit;
    font-weight: 600;
    line-height: inherit;
    color: rgb(15, 23, 42);
}

.onboarding-tips__title::after {
    content: ' – ';
    font-weight: 400;
    color: rgb(100, 116, 139);
}

.dark .onboarding-tips__title {
    color: rgb(248, 250, 252);
}

.dark .onboarding-tips__title::after {
    color: rgb(148, 163, 184);
}

.onboarding-tips__text {
    margin: 0;
    display: inline;
    font-size: inherit;
    line-height: inherit;
    font-weight: 400;
    color: rgb(71, 85, 105);
}

.dark .onboarding-tips__text {
    color: rgb(203, 213, 225);
}

.onboarding-tips__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.375rem;
    padding-top: 0.375rem;
    border-top: 1px solid rgba(59, 130, 246, 0.1);
}

.dark .onboarding-tips__footer {
    border-top-color: rgba(96, 165, 250, 0.15);
}

.onboarding-tips__dots {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    align-items: center;
}

.onboarding-tips__dot {
    width: 0.375rem;
    height: 0.375rem;
    padding: 0;
    border: none;
    border-radius: 9999px;
    background: rgba(59, 130, 246, 0.22);
    cursor: pointer;
    transition: width 0.25s ease, background-color 0.25s ease;
}

.onboarding-tips__dot:hover {
    background: rgba(59, 130, 246, 0.45);
}

.onboarding-tips__dot.is-active {
    width: 1.125rem;
    background: rgb(37, 99, 235);
}

.dark .onboarding-tips__dot {
    background: rgba(96, 165, 250, 0.3);
}

.dark .onboarding-tips__dot.is-active {
    background: rgb(96, 165, 250);
}

.onboarding-tips__counter {
    font-size: 0.6875rem;
    font-variant-numeric: tabular-nums;
    color: rgb(100, 116, 139);
    white-space: nowrap;
}

.dark .onboarding-tips__counter {
    color: rgb(148, 163, 184);
}

@media (prefers-reduced-motion: reduce) {
    .onboarding-tips__slide {
        transition: none;
    }
}

.onboarding-illustration-panel svg,
.onboarding-illustration-panel img,
.onboarding-illustration-svg {
    width: auto;
    height: auto;
    max-width: min(92%, 38rem);
    max-height: min(34vh, 18rem);
    filter: drop-shadow(0 18px 36px rgba(37, 99, 235, 0.14));
}

@media (min-width: 1024px) {
    .onboarding-illustration-panel svg,
    .onboarding-illustration-panel img,
    .onboarding-illustration-svg {
        max-width: min(90%, 44rem);
        max-height: min(50vh, 28rem);
    }
}

.onboarding-content-panel {
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: stretch;
    min-height: 0;
    padding: 1.5rem 1.25rem 1rem;
    overflow: hidden;
}

@media (min-width: 1024px) {
    .onboarding-content-panel {
        padding: 2rem 2.5rem 1.5rem 2rem;
    }
}

.onboarding-content-card {
    background: transparent;
    border-radius: 0;
    border: none;
    box-shadow: none;
    padding: 0;
    width: 100%;
    max-width: none;
    min-height: 0;
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.onboarding-step-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: stretch;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
}

@media (min-width: 640px) {
    .onboarding-step-body {
        align-items: center;
    }

    .onboarding-step-body > * {
        width: 100%;
        max-width: 36rem;
    }
}

@media (min-width: 1024px) {
    .onboarding-step-body > * {
        max-width: 42rem;
    }
}

.onboarding-step-header {
    margin-bottom: 1.25rem;
}

.onboarding-step-header h1 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    font-size: 1.625rem;
    line-height: 1.25;
    font-weight: 800;
    letter-spacing: -0.025em;
}

@media (min-width: 1024px) {
    .onboarding-step-header h1 {
        font-size: 2.125rem;
    }
}

.onboarding-step-header p {
    margin-top: 0;
    font-size: 1rem;
}

.onboarding-form-compact {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    min-height: 0;
}

.onboarding-form-compact .onboarding-form-input {
    padding: 0.875rem 1rem;
    font-size: 1.0625rem;
}

.onboarding-form-section-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
}

.dark .onboarding-form-section-title {
    color: #e5e7eb;
}

.onboarding-form-section {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.onboarding-form-section + .onboarding-form-section {
    margin-top: 0.125rem;
    padding-top: 1.125rem;
    border-top: 1px solid #e5e7eb;
}

.dark .onboarding-form-section + .onboarding-form-section {
    border-top-color: rgb(55 65 81);
}

.onboarding-form-section__hint {
    margin: -0.25rem 0 0;
    font-size: 0.875rem;
    line-height: 1.45;
    color: #6b7280;
}

.dark .onboarding-form-section__hint {
    color: #9ca3af;
}

.onboarding-form-divider {
    margin: 0.5rem 0 0;
    padding-top: 0.75rem;
    border-top: 1px solid #e5e7eb;
}

.dark .onboarding-form-divider {
    border-top-color: rgb(55 65 81);
}

.onboarding-company-card {
    padding: 1rem 1.125rem;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
}

.dark .onboarding-company-card {
    border-color: #4b5563;
    background: rgba(31, 41, 55, 0.55);
}

.onboarding-company-card__name {
    margin: 0 0 0.875rem;
    font-size: 1.125rem;
    font-weight: 600;
    line-height: 1.35;
    color: #111827;
}

.dark .onboarding-company-card__name {
    color: #f9fafb;
}

.onboarding-company-info {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.875rem 1rem;
    margin: 0;
    padding: 0;
    border: none;
    font-size: 0.9375rem;
}

.dark .onboarding-company-info {
    border: none;
}

.onboarding-company-info dt {
    font-size: 0.75rem;
    font-weight: 500;
    letter-spacing: 0.02em;
    text-transform: none;
    color: #6b7280;
}

.dark .onboarding-company-info dt {
    color: #9ca3af;
}

.onboarding-company-info dd {
    margin: 0.125rem 0 0;
    font-size: 0.9375rem;
    color: #374151;
    word-break: break-word;
}

.dark .onboarding-company-info dd {
    color: #e5e7eb;
}

.onboarding-company-info .is-full {
    grid-column: 1 / -1;
}

.onboarding-company-empty {
    margin: 0;
    padding: 0.875rem 1rem;
    border-radius: 0.75rem;
    border: 1px dashed #d1d5db;
    background: #f9fafb;
    font-size: 0.9375rem;
    line-height: 1.45;
    color: #6b7280;
}

.dark .onboarding-company-empty {
    border-color: #4b5563;
    background: rgba(31, 41, 55, 0.45);
    color: #9ca3af;
}

@media (max-width: 639px) {
    .onboarding-company-info {
        grid-template-columns: 1fr;
    }
}

.onboarding-form-fields {
    display: flex;
    flex-direction: column;
    gap: 1.125rem;
}

.onboarding-form-fields--grid {
    display: grid;
    gap: 1.125rem;
}

@media (min-width: 640px) {
    .onboarding-form-fields--grid-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.125rem 1rem;
    }
}

.onboarding-step-body .relative input,
.onboarding-step-body .relative select,
.onboarding-step-body .relative textarea {
    padding: 0.6875rem 0.875rem 0.5rem;
    font-size: 1.0625rem;
    line-height: 1.35;
}

.onboarding-step-body .relative label {
    font-size: 1rem;
}

.onboarding-step-body .relative textarea {
    min-height: 4.5rem;
}

.onboarding-form-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-top: 0.25rem;
    margin-top: 0.125rem;
    border-top: none;
}

.onboarding-form-actions a,
.onboarding-form-actions button[type="button"] {
    font-size: 1rem;
}

.dark .onboarding-form-actions {
    border-top: none;
}

.onboarding-btn-next {
    display: none;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    padding: 0.875rem 1.625rem;
    font-size: 1.0625rem;
    font-weight: 600;
    line-height: 1.25;
    color: #fff;
    background: #1d4ed8;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.onboarding-btn-next.is-visible {
    display: inline-flex;
}

.onboarding-btn-next.is-entering {
    animation: onboarding-btn-enter 0.4s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.onboarding-btn-next.is-visible:not(.is-loading):hover {
    background: #1e40af;
}

.onboarding-btn-next.is-visible:not(.is-loading):active {
    background: #1e3a8a;
}

@keyframes onboarding-btn-enter {
    0% {
        opacity: 0;
        transform: scale(0.94) translateY(6px);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.onboarding-btn-next:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.onboarding-btn-next.is-loading:disabled {
    opacity: 1;
    cursor: wait;
}

.onboarding-btn-next.is-loading {
    min-width: 9.5rem;
}

.onboarding-btn-next.is-loading .onboarding-btn-next__label,
.onboarding-btn-next.is-loading .onboarding-btn-next__arrow {
    display: none;
}

.onboarding-btn-next__spinner {
    display: none;
    align-items: center;
    justify-content: center;
}

.onboarding-btn-next.is-loading .onboarding-btn-next__spinner {
    display: inline-flex;
}

.onboarding-btn-next__label {
    letter-spacing: 0.01em;
}

.onboarding-btn-next__arrow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.125rem;
    height: 1.125rem;
    transition: transform 0.2s ease;
}

.onboarding-btn-next__arrow svg {
    width: 100%;
    height: 100%;
}

.onboarding-btn-next.is-entering .onboarding-btn-next__arrow {
    animation: onboarding-btn-arrow-nudge 0.45s ease 0.12s 1 both;
}

.onboarding-btn-next.is-visible:not(.is-loading):not(.is-entering):hover .onboarding-btn-next__arrow {
    transform: translateX(4px);
}

@keyframes onboarding-btn-arrow-nudge {
    0% {
        transform: translateX(-4px);
        opacity: 0.6;
    }
    100% {
        transform: translateX(0);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .onboarding-btn-next.is-entering {
        animation: none;
    }

    .onboarding-btn-next.is-entering .onboarding-btn-next__arrow {
        animation: none;
    }

    .onboarding-btn-next.is-visible:not(.is-loading):not(.is-entering):hover .onboarding-btn-next__arrow {
        transform: none;
    }
}

.dark .onboarding-btn-next {
    background: #2563eb;
}

.dark .onboarding-btn-next.is-visible:not(.is-loading):hover {
    background: #1d4ed8;
}

.dark .onboarding-btn-next.is-visible:not(.is-loading):active {
    background: #1e40af;
}

.onboarding-notice {
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 50;
    max-width: min(22rem, calc(100vw - 2rem));
    border-radius: 0.5rem;
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #991b1b;
}

.onboarding-notice.hidden {
    display: none;
}

.onboarding-notice__content {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
}

.onboarding-notice__message {
    margin: 0;
    flex: 1;
    min-width: 0;
    font-size: 1rem;
    line-height: 1.45;
}

.onboarding-notice__close {
    flex-shrink: 0;
    margin: -0.125rem -0.25rem 0 0;
    padding: 0.125rem;
    border: none;
    background: transparent;
    color: inherit;
    opacity: 0.65;
    cursor: pointer;
    line-height: 1;
}

.onboarding-notice__close:hover {
    opacity: 1;
}

.onboarding-notice__close svg {
    width: 1rem;
    height: 1rem;
    display: block;
}

.dark .onboarding-notice {
    border-color: rgba(248, 113, 113, 0.35);
    background: rgba(127, 29, 29, 0.35);
    color: #fecaca;
}

html.onboarding-page #toast-top-right,
html.onboarding-page #upload-progress-toast {
    display: none !important;
}

.onboarding-progress-wrap {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    min-height: 4.25rem;
    margin-bottom: 1rem;
}

@media (min-width: 640px) {
    .onboarding-progress-wrap {
        min-height: 4.75rem;
    }
}

.onboarding-progress-wrap ol {
    margin-bottom: 0;
    width: 100%;
}

.onboarding-progress-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
    margin: 0 auto 0.25rem;
    flex-shrink: 0;
    line-height: 1;
}

@media (min-width: 640px) {
    .onboarding-progress-icon {
        width: 1.5rem;
        height: 1.5rem;
        margin-bottom: 0.375rem;
    }

    .onboarding-progress-icon svg {
        width: 1.5rem;
        height: 1.5rem;
    }
}

.onboarding-progress-label {
    display: block;
    font-size: 0.6875rem;
    line-height: 1.2;
    white-space: nowrap;
}

@media (min-width: 640px) {
    .onboarding-progress-label {
        font-size: 0.8125rem;
    }
}

.onboarding-password-banner {
    padding: 1rem 1.125rem;
    margin-bottom: 0.375rem;
    font-size: 0.9375rem;
    line-height: 1.45;
    color: #1e40af;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 0.5rem;
    transition: opacity 0.2s ease, max-height 0.25s ease, margin 0.25s ease, padding 0.25s ease;
    overflow: hidden;
}

.dark .onboarding-password-banner {
    color: #93c5fd;
    background: rgba(30, 58, 138, 0.25);
    border-color: rgba(59, 130, 246, 0.35);
}

.onboarding-password-banner.is-hidden {
    opacity: 0;
    max-height: 0;
    margin-bottom: 0;
    padding-top: 0;
    padding-bottom: 0;
    border-width: 0;
    pointer-events: none;
}

.onboarding-password-banner__title {
    margin: 0 0 0.5rem;
    font-weight: 600;
    font-size: 0.9375rem;
}

.onboarding-password-banner__list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.onboarding-password-banner__item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    color: #64748b;
}

.dark .onboarding-password-banner__item {
    color: #94a3b8;
}

.onboarding-password-banner__item.is-met {
    color: #15803d;
}

.dark .onboarding-password-banner__item.is-met {
    color: #4ade80;
}

.onboarding-password-banner__marker {
    flex-shrink: 0;
    width: 1rem;
    height: 1rem;
    margin-top: 0.0625rem;
    border-radius: 9999px;
    border: 1.5px solid currentColor;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.onboarding-password-banner__item.is-met .onboarding-password-banner__marker {
    border-color: transparent;
    background: #16a34a;
    color: #fff;
}

.onboarding-password-banner__item.is-met .onboarding-password-banner__marker svg {
    width: 0.625rem;
    height: 0.625rem;
}

.onboarding-avatar-compact {
    gap: 1rem;
}

.onboarding-avatar-hero {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 0 0.25rem;
}

.onboarding-avatar-preview-wrap {
    position: relative;
}

.onboarding-avatar-compact .onboarding-avatar-preview {
    width: 4.5rem;
    height: 4.5rem;
    font-size: 1.375rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    flex-shrink: 0;
    border: none;
    box-shadow:
        0 0 0 3px #fff,
        0 0 0 4px #e5e7eb,
        0 4px 12px -2px rgb(0 0 0 / 0.1);
    transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

.dark .onboarding-avatar-compact .onboarding-avatar-preview {
    box-shadow:
        0 0 0 3px rgb(17 24 39),
        0 0 0 4px rgb(55 65 81),
        0 4px 12px -2px rgb(0 0 0 / 0.3);
}

.onboarding-avatar-preview-label {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 500;
    color: #6b7280;
}

.dark .onboarding-avatar-preview-label {
    color: #9ca3af;
}

.onboarding-avatar-panel {
    padding: 0.75rem 0.875rem;
    border-radius: 0.625rem;
    border: 1px solid #e5e7eb;
    background: #fff;
}

.dark .onboarding-avatar-panel {
    border-color: rgb(55 65 81);
    background: rgb(31 41 55 / 0.45);
}

.onboarding-avatar-panel__title {
    margin: 0 0 0.5rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #111827;
}

.dark .onboarding-avatar-panel__title {
    color: #f3f4f6;
}

.onboarding-avatar-panel__hint {
    margin: 0 0 0.625rem;
    font-size: 0.75rem;
    line-height: 1.45;
    color: #6b7280;
}

.dark .onboarding-avatar-panel__hint {
    color: #9ca3af;
}

.onboarding-color-grid {
    display: grid;
    grid-template-columns: repeat(8, minmax(0, 1fr));
    gap: 0.375rem;
    width: 100%;
}

@media (min-width: 480px) {
    .onboarding-color-grid {
        grid-template-columns: repeat(12, minmax(0, 1fr));
    }
}

.onboarding-color-swatch {
    position: relative;
    aspect-ratio: 1;
    width: 100%;
    padding: 0;
    border: 2px solid transparent;
    border-radius: 9999px;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.onboarding-color-swatch:hover {
    transform: scale(1.08);
}

.onboarding-color-swatch:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgb(37 99 235 / 0.45);
}

.onboarding-color-swatch.is-selected {
    border-color: #fff;
    box-shadow: 0 0 0 2px #2563eb;
    transform: scale(1.05);
}

.dark .onboarding-color-swatch.is-selected {
    border-color: rgb(17 24 39);
    box-shadow: 0 0 0 2px #3b82f6;
}

.onboarding-color-swatch__check {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: inherit;
    font-size: 0.6875rem;
    font-weight: 700;
    color: #fff;
    opacity: 0;
    transform: scale(0.6);
    transition: opacity 0.15s ease, transform 0.15s ease;
    text-shadow: 0 1px 2px rgb(0 0 0 / 0.35);
}

.onboarding-color-swatch.is-selected .onboarding-color-swatch__check {
    opacity: 1;
    transform: scale(1);
}

.onboarding-avatar-divider {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    color: #9ca3af;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.onboarding-avatar-divider::before,
.onboarding-avatar-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e5e7eb;
}

.dark .onboarding-avatar-divider::before,
.dark .onboarding-avatar-divider::after {
    background: rgb(55 65 81);
}

.onboarding-upload-zone {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.75rem 0.625rem;
    border: 1.5px dashed #d1d5db;
    border-radius: 0.5rem;
    background: #f9fafb;
    cursor: pointer;
    transition: border-color 0.15s ease, background-color 0.15s ease;
    text-align: center;
}

.onboarding-upload-zone:hover,
.onboarding-upload-zone.is-dragover {
    border-color: #2563eb;
    background: #eff6ff;
}

.dark .onboarding-upload-zone {
    border-color: rgb(75 85 99);
    background: rgb(17 24 39 / 0.35);
}

.dark .onboarding-upload-zone:hover,
.dark .onboarding-upload-zone.is-dragover {
    border-color: #3b82f6;
    background: rgb(37 99 235 / 0.08);
}

.onboarding-upload-zone__icon {
    width: 1.5rem;
    height: 1.5rem;
    color: #9ca3af;
}

.onboarding-upload-zone:hover .onboarding-upload-zone__icon,
.onboarding-upload-zone.is-dragover .onboarding-upload-zone__icon {
    color: #2563eb;
}

.dark .onboarding-upload-zone:hover .onboarding-upload-zone__icon,
.dark .onboarding-upload-zone.is-dragover .onboarding-upload-zone__icon {
    color: #60a5fa;
}

.onboarding-upload-zone__text {
    margin: 0;
    font-size: 0.8125rem;
    color: #374151;
}

.dark .onboarding-upload-zone__text {
    color: #d1d5db;
}

.onboarding-upload-zone__text strong {
    color: #2563eb;
    font-weight: 600;
}

.dark .onboarding-upload-zone__text strong {
    color: #60a5fa;
}

.onboarding-upload-zone__meta {
    margin: 0;
    font-size: 0.6875rem;
    color: #9ca3af;
}

.onboarding-upload-zone__filename {
    margin: 0.375rem 0 0;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: #2563eb;
    background: #eff6ff;
    border-radius: 9999px;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dark .onboarding-upload-zone__filename {
    color: #93c5fd;
    background: rgb(37 99 235 / 0.15);
}

.onboarding-upload-zone input[type="file"] {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.onboarding-avatar-compact .onboarding-form-actions {
    margin-top: 0.25rem;
}

.onboarding-anrede-field,
.onboarding-choice-field {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.onboarding-anrede-field__label,
.onboarding-choice-field__label {
    font-size: 0.9375rem;
    font-weight: 500;
    color: #6b7280;
}

.dark .onboarding-anrede-field__label,
.dark .onboarding-choice-field__label {
    color: #9ca3af;
}

.onboarding-anrede-optional,
.onboarding-choice-optional {
    font-weight: 400;
    color: #9ca3af;
}

.dark .onboarding-anrede-optional,
.dark .onboarding-choice-optional {
    color: #6b7280;
}

.onboarding-anrede-chips,
.onboarding-choice-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.onboarding-anrede-chip,
.onboarding-choice-chip {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 400;
    line-height: 1.3;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    transition: border-color 0.12s ease, background-color 0.12s ease, color 0.12s ease;
}

.dark .onboarding-anrede-chip,
.dark .onboarding-choice-chip {
    border-color: #4b5563;
    background: transparent;
    color: #9ca3af;
}

.onboarding-anrede-chip:hover:not(.is-selected),
.onboarding-choice-chip:hover:not(.is-selected) {
    border-color: #d1d5db;
    background: #f3f4f6;
    color: #4b5563;
}

.dark .onboarding-anrede-chip:hover:not(.is-selected),
.dark .onboarding-choice-chip:hover:not(.is-selected) {
    border-color: #6b7280;
    background: rgba(55, 65, 81, 0.5);
    color: #d1d5db;
}

.onboarding-anrede-chip.is-selected,
.onboarding-choice-chip.is-selected {
    border-color: #9ca3af;
    background: #e5e7eb;
    color: #111827;
    font-weight: 500;
    box-shadow: inset 0 0 0 1px rgba(17, 24, 39, 0.04);
}

.dark .onboarding-anrede-chip.is-selected,
.dark .onboarding-choice-chip.is-selected {
    border-color: #9ca3af;
    background: rgba(75, 85, 99, 0.85);
    color: #f9fafb;
    font-weight: 500;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
}

.onboarding-anrede-chip:focus-visible,
.onboarding-choice-chip:focus-visible {
    outline: none;
    border-color: #9ca3af;
}

.onboarding-erreichbarkeit {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.onboarding-erreichbarkeit-layout {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.onboarding-erreichbarkeit-times-col {
    display: none;
}

.onboarding-erreichbarkeit-times-col.is-visible {
    display: block;
    animation: onboarding-erreichbarkeit-times-in 0.22s ease;
}

@media (min-width: 1024px) {
    .onboarding-erreichbarkeit-layout {
        flex-direction: row;
        align-items: center;
        gap: 0.75rem;
    }
}

@keyframes onboarding-erreichbarkeit-times-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.onboarding-erreichbarkeit-block__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    min-height: 1.375rem;
    margin-bottom: 0;
}

.onboarding-erreichbarkeit-block__label {
    font-size: 0.9375rem;
    font-weight: 500;
    color: #6b7280;
}

.dark .onboarding-erreichbarkeit-block__label {
    color: #9ca3af;
}

.onboarding-erreichbarkeit-quick {
    padding: 0;
    border: none;
    background: transparent;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.onboarding-erreichbarkeit-quick:hover {
    color: #374151;
}

.dark .onboarding-erreichbarkeit-quick {
    color: #9ca3af;
}

.dark .onboarding-erreichbarkeit-quick:hover {
    color: #e5e7eb;
}

.onboarding-erreichbarkeit-days {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.onboarding-erreichbarkeit-times__row {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: 0.5rem 0.75rem;
}

.onboarding-erreichbarkeit-times__row .time-wheel-group--onboarding {
    flex-direction: row;
    align-items: center;
    gap: 0.625rem;
}

.onboarding-erreichbarkeit-times__row .time-wheel-group--onboarding .time-wheel-group__label {
    white-space: nowrap;
}

@media (min-width: 1024px) {
    .onboarding-erreichbarkeit-times__row .time-wheel-trigger {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 400;
        line-height: 1.3;
        border-radius: 0.375rem;
        min-width: 4.25rem;
    }

    .onboarding-erreichbarkeit-times__row .time-wheel-group--onboarding .time-wheel-group__label {
        font-size: 0.875rem;
        line-height: 1.3;
    }
}

@media (max-width: 1023px) {
    .onboarding-split {
        grid-template-rows: auto minmax(0, 1fr);
    }

    .onboarding-illustration-panel {
        max-height: 30vh;
        padding: 1rem 1.25rem;
    }

    .onboarding-illustration-panel svg,
    .onboarding-illustration-panel img,
    .onboarding-illustration-svg {
        max-height: 26vh;
        max-width: 85%;
    }

    .onboarding-content-panel {
        padding: 0.875rem 1rem 1rem;
    }

    .onboarding-step-header {
        margin-bottom: 0.75rem;
    }

    .onboarding-step-header h1 {
        font-size: 1.375rem;
    }
}

/* Smartphone: Illustration ausblenden, nur Formular */
@media (max-width: 767px) {
    .onboarding-illustration-panel {
        display: none;
    }

    .onboarding-split {
        grid-template-rows: minmax(0, 1fr);
    }
}

/* Schritt 3 (Kontakt): auf Mobilgeräten kompakt, leicht unterhalb der Mitte */
@media (max-width: 1023px) {
    .onboarding-shell--contact .onboarding-step-body.onboarding-step--contact {
        justify-content: flex-start;
        align-items: stretch;
        padding-top: 1rem;
    }

    .onboarding-shell--contact .onboarding-progress-wrap {
        min-height: 3.5rem;
        margin-bottom: 1rem;
    }

    .onboarding-shell--contact .onboarding-step-header {
        margin-bottom: 0.875rem;
    }

    .onboarding-shell--contact .onboarding-form-compact {
        gap: 1rem;
    }

    .onboarding-shell--contact .onboarding-form-section + .onboarding-form-section {
        margin-top: 0;
        padding-top: 0.875rem;
    }

    .onboarding-shell--contact .onboarding-illustration-panel {
        max-height: 20vh;
        padding: 0.5rem 1rem;
    }

    .onboarding-shell--contact .onboarding-illustration-panel svg,
    .onboarding-shell--contact .onboarding-illustration-panel img,
    .onboarding-shell--contact .onboarding-illustration-svg {
        max-height: 17vh;
    }
}

@media (max-width: 767px) {
    .onboarding-shell--contact .onboarding-content-panel {
        padding-top: 1.5rem;
    }

    .onboarding-shell--contact .onboarding-step-body.onboarding-step--contact {
        padding-top: 1.5rem;
    }

    .onboarding-shell--contact .onboarding-progress-wrap {
        min-height: 3.25rem;
        margin-bottom: 0.875rem;
    }

    .onboarding-shell--contact .onboarding-step-header h1 {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
    }

    .onboarding-shell--contact .onboarding-step-header p {
        font-size: 0.875rem;
    }
}

.onboarding-form-input {
    display: block;
    width: 100%;
    padding: 0.875rem 1rem;
    font-size: 1.0625rem;
    line-height: 1.5;
    color: #111827;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    background: #f9fafb;
}

.onboarding-form-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.dark .onboarding-form-input {
    color: white;
    background: rgb(55 65 81);
    border-color: rgb(75 85 99);
}
</style>
    <?php
}

function onboarding_step_count(): int
{
    return 4;
}

function onboarding_step_is_accessible(int $step, array $status): bool
{
    if ($step === 1) {
        return true;
    }
    if ($step === 2) {
        return !empty($status['step1_completed']);
    }
    if ($step === 3) {
        return !empty($status['step1_completed']) && !empty($status['step2_completed']);
    }
    if ($step === 4) {
        return !empty($status['step1_completed'])
            && !empty($status['step2_completed'])
            && !empty($status['step3_completed']);
    }
    return false;
}

function onboarding_layout_body_script(): void
{
    ?>
<script>
document.documentElement.classList.add('onboarding-page');

window.onboardingInitTipsCarousel = function() {
    var root = document.querySelector('[data-onboarding-tips]');
    if (!root || root.dataset.tipsInit) return;
    root.dataset.tipsInit = '1';

    var slides = Array.from(root.querySelectorAll('.onboarding-tips__slide'));
    var dots = Array.from(root.querySelectorAll('[data-tip-dot]'));
    var counter = root.querySelector('[data-onboarding-tips-counter]');
    if (!slides.length) return;

    var storageKey = 'serohub_onboarding_tip_index';
    var intervalMs = 7000;
    var timer = null;
    var total = slides.length;
    var index = 0;

    try {
        var stored = parseInt(sessionStorage.getItem(storageKey) || '0', 10);
        if (!isNaN(stored) && stored >= 0 && stored < total) {
            index = stored;
        }
    } catch (e) {}

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function persistIndex() {
        try {
            sessionStorage.setItem(storageKey, String(index));
        } catch (e) {}
    }

    function updateCounter() {
        if (counter) {
            counter.textContent = (index + 1) + ' / ' + total;
        }
    }

    function setActive(nextIndex, animate) {
        if (nextIndex < 0) nextIndex = total - 1;
        if (nextIndex >= total) nextIndex = 0;
        if (nextIndex === index && slides[index].classList.contains('is-active')) {
            return;
        }

        var prev = index;
        index = nextIndex;
        persistIndex();
        updateCounter();

        dots.forEach(function(dot, i) {
            var active = i === index;
            dot.classList.toggle('is-active', active);
            dot.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        if (!animate || prefersReducedMotion()) {
            slides.forEach(function(slide, i) {
                var active = i === index;
                slide.classList.toggle('is-active', active);
                slide.classList.remove('is-leaving');
                slide.setAttribute('aria-hidden', active ? 'false' : 'true');
            });
            return;
        }

        var outgoing = slides[prev];
        var incoming = slides[index];
        outgoing.classList.remove('is-active');
        outgoing.classList.add('is-leaving');
        outgoing.setAttribute('aria-hidden', 'true');

        incoming.classList.add('is-active');
        incoming.setAttribute('aria-hidden', 'false');

        window.setTimeout(function() {
            outgoing.classList.remove('is-leaving');
            slides.forEach(function(slide, i) {
                if (i !== index) {
                    slide.classList.remove('is-active', 'is-leaving');
                    slide.setAttribute('aria-hidden', 'true');
                }
            });
        }, 460);
    }

    function nextTip() {
        setActive(index + 1, true);
    }

    function startTimer() {
        if (timer) clearInterval(timer);
        if (prefersReducedMotion()) return;
        timer = setInterval(nextTip, intervalMs);
    }

    function resetTimer() {
        startTimer();
    }

    dots.forEach(function(dot) {
        dot.addEventListener('click', function() {
            var target = parseInt(dot.getAttribute('data-tip-dot') || '0', 10);
            if (!isNaN(target)) {
                setActive(target, true);
                resetTimer();
            }
        });
    });

    root.addEventListener('mouseenter', function() {
        if (timer) clearInterval(timer);
    });
    root.addEventListener('mouseleave', startTimer);

    setActive(index, false);
    startTimer();
};

document.addEventListener('DOMContentLoaded', function() {
    window.onboardingInitTipsCarousel();
});

window.onboardingSetNextVisible = function(btn, visible) {
    if (!btn) return;
    visible = !!visible;
    if (btn.dataset.onboardingVisible === (visible ? '1' : '0')) {
        return;
    }
    btn.dataset.onboardingVisible = visible ? '1' : '0';

    var isVisible = btn.classList.contains('is-visible');
    if (visible) {
        if (!isVisible) {
            btn.classList.add('is-visible', 'is-entering');
            btn.addEventListener('animationend', function onOnboardingBtnEnterEnd(e) {
                if (e.target !== btn || e.animationName !== 'onboarding-btn-enter') return;
                btn.classList.remove('is-entering');
                btn.removeEventListener('animationend', onOnboardingBtnEnterEnd);
            });
        }
    } else if (isVisible) {
        btn.classList.remove('is-visible', 'is-entering');
    }
};
window.onboardingFormHasInput = function(form) {
    if (!form) return false;
    return Array.from(form.querySelectorAll('input:not([type=hidden]), select, textarea')).some(function(el) {
        return String(el.value || '').trim() !== '';
    });
};
window.onboardingInitFormSnapshot = function(form) {
    if (!form || form.dataset.snapshotInit) return;
    form.dataset.snapshotInit = '1';
    Array.from(form.querySelectorAll('input:not([type=hidden]), select, textarea, [data-onboarding-choice] input[type=hidden], [data-erreichbarkeit] input[type=hidden]')).forEach(function(el) {
        el.dataset.initialValue = el.value;
    });
};
window.onboardingFormIsDirty = function(form) {
    if (!form) return false;
    return Array.from(form.querySelectorAll('input:not([type=hidden]), select, textarea, [data-onboarding-choice] input[type=hidden], [data-erreichbarkeit] input[type=hidden]')).some(function(el) {
        return String(el.value || '') !== String(el.dataset.initialValue || '');
    });
};
window.onboardingInitChoiceFields = function(form) {
    if (!form) return;
    form.querySelectorAll('[data-onboarding-choice]').forEach(function(wrap) {
        if (wrap.dataset.choiceInit) return;
        wrap.dataset.choiceInit = '1';
        var hidden = wrap.querySelector('input[type=hidden][id]');
        if (!hidden) return;
        var mobile = wrap.querySelector('[data-choice-mobile]');
        var chips = wrap.querySelectorAll('.onboarding-choice-chip, .onboarding-anrede-chip');
        function setValue(value) {
            hidden.value = value;
            if (mobile) mobile.value = value;
            chips.forEach(function(chip) {
                var selected = chip.dataset.value === value;
                chip.classList.toggle('is-selected', selected);
                chip.setAttribute('aria-checked', selected ? 'true' : 'false');
            });
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (mobile) {
            mobile.addEventListener('change', function() {
                setValue(mobile.value);
            });
        }
        chips.forEach(function(chip) {
            chip.addEventListener('click', function() {
                setValue(chip.dataset.value || '');
            });
        });
    });
};

var onboardingNoticeTimer = null;
window.onboardingHideNotice = function() {
    var el = document.getElementById('onboarding-notice');
    if (el) el.classList.add('hidden');
    if (onboardingNoticeTimer) {
        clearTimeout(onboardingNoticeTimer);
        onboardingNoticeTimer = null;
    }
};
window.onboardingShowNotice = function(message) {
    var el = document.getElementById('onboarding-notice');
    var msg = document.getElementById('onboarding-notice-message');
    if (!el || !msg || !message) return;
    msg.textContent = message;
    el.classList.remove('hidden');
    if (onboardingNoticeTimer) clearTimeout(onboardingNoticeTimer);
    onboardingNoticeTimer = setTimeout(onboardingHideNotice, 9000);
};
window.onboardingSpinnerMarkup = <?php echo json_encode(onboarding_spinner_markup('w-6 h-6'), JSON_UNESCAPED_UNICODE); ?>;
window.onboardingBtnSetLoading = function(btn, loading) {
    if (!btn) return;
    if (btn.classList.contains('onboarding-btn-next')) {
        btn.disabled = !!loading;
        btn.classList.toggle('is-loading', !!loading);
        btn.setAttribute('aria-busy', loading ? 'true' : 'false');
        return;
    }
    if (loading) {
        if (!btn.dataset.onboardingIdleHtml) {
            btn.dataset.onboardingIdleHtml = btn.innerHTML;
        }
        btn.disabled = true;
        btn.innerHTML = window.onboardingSpinnerMarkup;
        btn.setAttribute('aria-busy', 'true');
    } else {
        btn.disabled = false;
        btn.setAttribute('aria-busy', 'false');
        if (btn.dataset.onboardingIdleHtml) {
            btn.innerHTML = btn.dataset.onboardingIdleHtml;
            delete btn.dataset.onboardingIdleHtml;
        }
    }
};
window.showToast = function(message, type) {
    if (type === 'error' || type === 'warning') {
        onboardingShowNotice(message);
    }
};
</script>
    <?php
}

function onboarding_render_notice(): void
{
    ?>
<div id="onboarding-notice" class="onboarding-notice hidden" role="alert" aria-live="polite">
    <div class="onboarding-notice__content">
        <p id="onboarding-notice-message" class="onboarding-notice__message"></p>
        <button type="button" class="onboarding-notice__close" onclick="onboardingHideNotice()" aria-label="Schließen">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
    <?php
}
function onboarding_get_current_step(array $status): int
{
    if (empty($status['step1_completed'])) {
        return 1;
    }
    if (empty($status['step2_completed'])) {
        return 2;
    }
    if (empty($status['step3_completed'])) {
        return 3;
    }
    if (empty($status['step4_completed'])) {
        return 4;
    }
    return 0;
}

function onboarding_step_url(int $step): string
{
    return BASE_URL . 'onboarding/step' . $step . '.php';
}

function onboarding_redirect_to_current_step(array $status): void
{
    $currentStep = onboarding_get_current_step($status);
    if ($currentStep > 0) {
        header('Location: ' . onboarding_step_url($currentStep));
        exit;
    }
}

function onboarding_can_finish(array $status): bool
{
    return !empty($status['step1_completed'])
        && !empty($status['step2_completed'])
        && !empty($status['step3_completed'])
        && !empty($status['step4_completed']);
}

function onboarding_completed_count(array $status): int
{
    $count = 0;
    $total = onboarding_step_count();
    for ($i = 1; $i <= $total; $i++) {
        if (!empty($status['step' . $i . '_completed'])) {
            $count++;
        }
    }
    return $count;
}

function onboarding_progress_percent(int $currentStep, int $completedCount): int
{
    $total = onboarding_step_count();
    if ($currentStep === 0) {
        return (int) round(($completedCount / $total) * 100);
    }
    return (int) round(($currentStep / $total) * 100);
}

function onboarding_render_illustration(string $type): void
{
    if ($type === 'step4') {
        $type = 'step3';
    }

    $illustrations = [
        'welcome' => <<<'SVG'
<svg class="w-auto max-w-[16rem] h-40 text-gray-800 dark:text-white" aria-hidden="true" width="621" height="608" viewBox="0 0 621 608" fill="none" xmlns="http://www.w3.org/2000/svg">
<circle cx="310" cy="227" r="227" fill="url(#paint0_linear_275_984)"/>
<path d="M426.132 542.339L429.531 504.028L459.163 494.703C459.193 503.091 462.424 521.964 475.107 530.351C487.79 538.738 491.642 546.29 491.983 549.017L426.132 542.339Z" fill="#374151"/>
<path d="M426.132 542.339L429.531 504.028L459.163 494.703C459.193 503.091 462.424 521.964 475.107 530.351C487.79 538.738 491.642 546.29 491.983 549.017L426.132 542.339Z" fill="url(#paint1_linear_275_984)"/>
<rect x="426.066" y="541.828" width="66.4241" height="8" transform="rotate(5.76679 426.066 541.828)" fill="#2563eb"/>
<path d="M477.863 506.321L478.004 406.45L422.681 421.432L423.629 504.919C423.641 505.992 424.499 506.865 425.572 506.895L475.806 508.317C476.932 508.349 477.861 507.446 477.863 506.321Z" fill="#2563eb"/>
<path d="M477.863 506.321L478.004 406.45L422.681 421.432L423.629 504.919C423.641 505.992 424.499 506.865 425.572 506.895L475.806 508.317C476.932 508.349 477.861 507.446 477.863 506.321Z" fill="url(#paint2_linear_275_984)"/>
<path d="M152.299 314.642L197.344 383.81L333.375 360.39C275.415 297.097 188.508 303.52 152.299 314.642Z" fill="#c8d8fa"/>
<path d="M152.299 314.642L197.344 383.81L333.375 360.39C275.415 297.097 188.508 303.52 152.299 314.642Z" fill="url(#paint3_linear_275_984)"/>
<path d="M209.188 606.919L225.001 525.905L359.886 496.595C329.896 577.006 246.925 603.649 209.188 606.919Z" fill="#c8d8fa"/>
<path d="M209.188 606.919L225.001 525.905L359.886 496.595C329.896 577.006 246.925 603.649 209.188 606.919Z" fill="url(#paint4_linear_275_984)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M603.551 370.908C605.188 372.867 606.239 375.24 606.726 377.746C607.214 380.252 607.13 382.846 606.347 385.276C598.322 410.193 581.651 429.367 563.856 443.169C545.187 457.649 525.219 466.357 512.51 469.943L512.49 469.949L512.47 469.953L225.492 525.809L225.001 525.905L224.905 525.412L222.988 515.563L221.838 509.653L197.394 384.068L197.298 383.576L197.79 383.48L484.767 327.623L484.787 327.62L484.808 327.617C497.934 326.175 519.711 326.758 542.447 333.179C564.12 339.299 586.766 350.82 603.551 370.908Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M418.456 488.251L457.289 349.5L428.598 338.556L197.789 383.48L197.298 383.576L197.394 384.068L221.837 509.653L222.988 515.563L224.905 525.412L225.001 525.905L225.492 525.809L418.456 488.251Z" fill="url(#paint5_linear_275_984)"/>
<path d="M197.298 383.576L225.001 525.905L193.643 521.821L169.761 399.123L197.298 383.576Z" fill="#c8d8fa"/>
<path d="M0 421.978L27.7027 564.307L193.642 521.821L169.761 399.123L0 421.978Z" fill="url(#paint6_linear_275_984)"/>
<path d="M606.631 377.255L211.054 454.25" stroke="#9ab7f6" stroke-width="2"/>
<path d="M606.631 377.255L211.054 454.25" stroke="url(#paint7_linear_275_984)" stroke-width="2"/>
<path d="M267.905 102.921C286.053 95.341 295.799 105.98 298.486 112.04C297.208 125.763 295.15 160.188 281.406 157.921C263.906 145.921 240.405 120.421 267.905 102.921Z" fill="#111928"/>
<path d="M234.171 117.595C243.706 123.862 255.824 122.264 261.237 114.028C266.65 105.791 263.308 94.0341 253.772 87.7677C244.237 81.5013 232.119 83.0985 226.706 91.3352C221.293 99.5718 224.635 111.329 234.171 117.595Z" fill="#111928"/>
<path d="M294.406 176.65L295.06 152.45L281.548 143.594L275.988 171.984C279.968 175.547 290.425 176.579 294.406 176.65Z" fill="#FDBA8C"/>
<path d="M294.406 176.65L295.06 152.45L281.548 143.594L275.988 171.984C279.968 175.547 290.425 176.579 294.406 176.65Z" fill="url(#paint8_linear_275_984)"/>
<path d="M271.923 132.933C269.461 135.761 282.498 158.393 298.319 153.735C303.787 152.126 305.536 146.428 305.337 139.48C305.322 138.962 305.892 139.494 305.856 138.963C305.822 138.446 305.182 136.868 305.129 136.342C304.273 127.872 301.119 118.267 298.487 112.039C296.269 114.029 291.644 119.591 290.887 125.919C289.942 133.828 284.599 137.179 281.194 134.821C277.528 132.281 274.386 130.105 271.923 132.933Z" fill="#FDBA8C"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M303.026 128.824C302.672 128.944 302.482 129.329 302.603 129.683L305.606 138.506C305.66 138.663 305.555 138.83 305.39 138.85L301.425 139.325C301.054 139.369 300.789 139.706 300.833 140.078C300.878 140.449 301.215 140.714 301.586 140.67L305.551 140.195C306.571 140.073 307.22 139.042 306.889 138.069L303.885 129.247C303.765 128.892 303.38 128.703 303.026 128.824Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M301.008 144.958C300.146 144.912 299.229 144.717 297.827 144.383C297.463 144.297 297.098 144.522 297.011 144.886C296.925 145.25 297.15 145.615 297.514 145.701C298.903 146.032 299.934 146.257 300.935 146.311C301.955 146.366 302.914 146.241 304.181 145.921C304.544 145.83 304.764 145.461 304.672 145.099C304.581 144.736 304.212 144.516 303.85 144.608C302.668 144.906 301.85 145.003 301.008 144.958Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M276.431 138.591C275.886 137.758 275.785 136.852 275.85 135.887C275.876 135.514 275.594 135.19 275.22 135.165C274.847 135.14 274.524 135.422 274.499 135.795C274.423 136.91 274.527 138.154 275.297 139.332C276.063 140.505 277.423 141.515 279.654 142.309C280.007 142.435 280.394 142.251 280.52 141.899C280.645 141.546 280.461 141.159 280.109 141.033C278.043 140.297 276.98 139.431 276.431 138.591Z" fill="#111928"/>
<path d="M369.789 556.5L386.789 522L417.789 524C414.789 531.833 410.989 550.6 419.789 563C428.589 575.4 429.456 583.833 428.789 586.5L369.789 556.5Z" fill="#374151"/>
<path d="M369.789 556.5L386.789 522L417.789 524C414.789 531.833 410.989 550.6 419.789 563C428.589 575.4 429.456 583.833 428.789 586.5L369.789 556.5Z" fill="url(#paint9_linear_275_984)"/>
<rect x="369.912" y="556" width="66.4241" height="8" transform="rotate(26.928 369.912 556)" fill="#2563eb"/>
<path d="M344.289 317L329.789 286.5L277.289 284.5C273.955 291.5 268.889 314.8 275.289 352C281.689 389.2 309.289 398 332.789 393.5L432.789 374L380.918 520.72C380.565 521.719 381.052 522.821 382.029 523.231L428.36 542.69C429.411 543.131 430.618 542.606 431.011 541.537L494.922 367.851C504.684 341.319 484.591 313.308 456.33 314.052L344.289 317Z" fill="#2563eb"/>
<path d="M344.289 317L329.789 286.5L277.289 284.5C273.955 291.5 268.889 314.8 275.289 352C281.689 389.2 309.289 398 332.789 393.5L432.789 374L380.918 520.72C380.565 521.719 381.052 522.821 382.029 523.231L428.36 542.69C429.411 543.131 430.618 542.606 431.011 541.537L494.922 367.851C504.684 341.319 484.591 313.308 456.33 314.052L344.289 317Z" fill="url(#paint10_linear_275_984)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M273.451 339.156C273.374 338.479 273.301 337.807 273.232 337.141C278.544 337.419 284.928 338.528 291.852 340.432C302.868 343.462 315.368 348.538 327.3 355.641C347.778 367.83 368.529 359.136 390.54 348.475C391.941 347.796 393.347 347.11 394.757 346.422L394.765 346.418C404.358 341.735 414.165 336.948 424.147 333.751C435.621 330.076 447.416 328.469 459.533 331.53C478.252 336.259 490.122 343.862 497.346 351.579C497.409 352.601 497.432 353.63 497.414 354.664C490.702 346.705 478.891 338.484 459.043 333.47C447.411 330.531 436.018 332.049 424.757 335.656C414.924 338.805 405.259 343.522 395.653 348.21L395.646 348.213C394.234 348.903 392.822 349.592 391.412 350.275C369.548 360.864 347.798 370.17 326.277 357.359C314.505 350.352 302.171 345.345 291.322 342.361C284.595 340.51 278.483 339.45 273.451 339.156ZM291.499 383.951C290.976 383.493 290.461 383.018 289.955 382.527C295.211 379.5 301.845 377.037 309.395 376.428C320.774 375.51 334.14 378.809 347.9 390.553L345.371 391.046C332.427 380.46 320.029 377.577 309.556 378.422C302.586 378.984 296.43 381.201 291.499 383.951ZM425.896 393.496L426.904 390.646C433.402 387.094 440.873 383.657 448.774 381.038C462.411 376.518 477.459 374.394 491.016 378.465L490.323 380.346C477.356 376.491 462.823 378.489 449.403 382.937C440.815 385.783 432.736 389.616 425.896 393.496ZM406.46 448.472L407.314 446.057C417.595 462.164 419.662 479.981 417.794 496.189C416.133 510.607 411.355 523.796 406.43 533.479L404.581 532.703C409.432 523.199 414.168 510.182 415.807 495.96C417.585 480.538 415.717 463.751 406.46 448.472ZM448.347 494.424L446.637 499.071C446.215 487.651 443.552 477.669 440.47 468.894C439.008 464.732 437.452 460.844 435.99 457.188L435.989 457.187L435.807 456.732C434.296 452.953 432.888 449.412 431.821 446.112C429.698 439.547 428.831 433.678 431.394 428.553C433.413 424.515 436.97 421.383 441.304 418.964C445.64 416.545 450.813 414.805 456.164 413.575C463.993 411.777 472.286 411.052 479.072 410.924L478.33 412.941C471.81 413.106 463.989 413.83 456.612 415.525C451.372 416.729 446.395 418.414 442.279 420.711C438.161 423.009 434.964 425.885 433.183 429.447C430.995 433.822 431.628 439.016 433.724 445.497C434.767 448.721 436.148 452.199 437.664 455.989L437.85 456.454C439.31 460.103 440.88 464.027 442.357 468.231C445.072 475.962 447.475 484.648 448.347 494.424ZM411.533 315.23L415.616 315.123C407.705 319.892 397.713 325.539 387.681 330.497C380.841 333.877 373.966 336.945 367.711 339.194C361.475 341.437 355.776 342.896 351.311 343C329.299 343.512 308.827 320.408 312.621 285.846L314.624 285.922C310.836 319.804 330.848 341.475 351.265 341C355.401 340.904 360.855 339.534 367.034 337.312C373.193 335.097 379.994 332.065 386.795 328.704C395.521 324.391 404.222 319.551 411.533 315.23Z" fill="#c8d8fa"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M399.372 530.515L463.288 368L426.513 375.224L432.788 374L380.918 520.72C380.564 521.719 381.052 522.821 382.029 523.231L399.372 530.515Z" fill="url(#paint11_linear_275_984)"/>
<path d="M408.093 241.423C404.473 241.548 388.333 244.093 380.715 245.35L379.382 262.799C384.712 260.866 396.996 256.723 403.499 255.615C411.628 254.23 414.928 256.989 421.025 255.95C427.122 254.911 428.149 241.451 424.199 240.648C420.249 239.844 412.618 241.267 408.093 241.423Z" fill="#FDBA8C"/>
<path d="M408.093 241.423C404.473 241.548 388.333 244.093 380.715 245.35L379.382 262.799C384.712 260.866 396.996 256.723 403.499 255.615C411.628 254.23 414.928 256.989 421.025 255.95C427.122 254.911 428.149 241.451 424.199 240.648C420.249 239.844 412.618 241.267 408.093 241.423Z" fill="url(#paint12_linear_275_984)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M320.289 289C349.281 283.59 386.386 272.316 407.289 265.212C407.289 258.489 401.151 245.603 398.081 240L320.289 250.122V289Z" fill="#F9FAFB"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M320.289 289C349.281 283.59 386.386 272.316 407.289 265.212C407.289 258.489 401.151 245.603 398.081 240L320.289 250.122V289Z" fill="url(#paint13_linear_275_984)"/>
<path d="M427.789 252.5H425.789C423.289 262 417.789 269.5 414.789 261.5C412.764 256.1 415.825 244.948 417.888 238.009C418.42 236.22 420.069 235 421.934 235H422.097C423.723 235 425.185 235.99 425.789 237.5H435.789L436.907 236.382C437.792 235.497 438.992 235 440.244 235C443.315 235 445.565 237.884 444.792 240.856C443.616 245.376 442.096 251.057 440.789 255.5C438.289 264 434.789 267 431.789 265C428.812 263.015 427.789 256 427.789 252.5Z" fill="#111928"/>
<path d="M430.289 242C426.689 242.4 410.789 246.167 403.289 248V265.5C408.456 263.167 420.389 258.1 426.789 256.5C434.789 254.5 438.289 257 444.289 255.5C450.289 254 450.289 240.5 446.289 240C442.289 239.5 434.789 241.5 430.289 242Z" fill="#FDBA8C"/>
<path d="M430.289 242C426.689 242.4 410.789 246.167 403.289 248V265.5C408.456 263.167 420.389 258.1 426.789 256.5C434.789 254.5 438.289 257 444.289 255.5C450.289 254 450.289 240.5 446.289 240C442.289 239.5 434.789 241.5 430.289 242Z" fill="url(#paint14_linear_275_984)"/>
<path d="M264.289 303V241L303.789 297.5L264.289 303Z" fill="#F9FAFB"/>
<path d="M264.289 303V241L303.789 297.5L264.289 303Z" fill="url(#paint15_linear_275_984)"/>
<path d="M414.289 272C414.289 264.8 408.622 251 405.789 245L326.289 257C325.956 250.333 324.889 233.2 323.289 218C321.727 203.162 306.379 179.81 298.362 169.249C298.022 168.801 297.428 168.644 296.906 168.853C290.305 171.494 282.895 171.219 276.508 168.096L276.058 167.876C275.591 167.647 275.03 167.712 274.662 168.08C271.177 171.564 263.924 182.709 258.289 204.5C250.789 233.5 277.789 297.5 302.789 304C322.789 309.2 385.122 283.5 414.289 272Z" fill="#F9FAFB"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M323.447 219.524L290.789 214.5L310.789 259.5L326.289 257C325.967 250.555 324.959 234.329 323.447 219.524Z" fill="url(#paint16_linear_275_984)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M287.842 133.384L296.09 131.912L295.719 128.943L294.977 125.603L290.74 126.927L275.482 131.695C277.119 132.036 278.936 133.258 280.907 134.622L287.842 133.384Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M287.842 133.384L296.09 131.912L295.719 128.943L294.977 125.603L290.74 126.927L275.482 131.695C277.119 132.036 278.936 133.258 280.907 134.622L287.842 133.384Z" fill="url(#paint17_linear_275_984)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M297.654 121.126C298.961 120.749 302.51 120.036 304.255 120.036L303.513 121.892L302.437 121.757C306.06 130.494 308.325 137.32 307.967 139.706C307.472 140.077 304.255 141.561 302.771 141.561C301.241 141.561 299.06 140.819 297.204 138.221C295.349 135.623 292.38 124.119 294.978 122.263C295.737 121.721 296.685 121.36 297.654 121.126Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M297.654 121.126C298.961 120.749 302.51 120.036 304.255 120.036L303.513 121.892L302.437 121.757C306.06 130.494 308.325 137.32 307.967 139.706C307.472 140.077 304.255 141.561 302.771 141.561C301.241 141.561 299.06 140.819 297.204 138.221C295.349 135.623 292.38 124.119 294.978 122.263C295.737 121.721 296.685 121.36 297.654 121.126Z" fill="url(#paint18_linear_275_984)"/>
<path d="M298.688 121.521C300.134 120.488 302.261 120.114 303.922 120.024C305.038 119.963 306.017 120.681 306.417 121.726C309.173 128.94 309.69 134.77 309.56 137.74C309.525 138.528 309.103 139.24 308.417 139.628C307.845 139.951 307.148 140.281 306.482 140.448C304.997 140.819 302.771 140.077 300.915 137.479C299.06 134.881 296.091 123.376 298.688 121.521Z" fill="#111928"/>
<path d="M298.688 121.521C300.134 120.488 302.261 120.114 303.922 120.024C305.038 119.963 306.017 120.681 306.417 121.726C309.173 128.94 309.69 134.77 309.56 137.74C309.525 138.528 309.103 139.24 308.417 139.628C307.845 139.951 307.148 140.281 306.482 140.448C304.997 140.819 302.771 140.077 300.915 137.479C299.06 134.881 296.091 123.376 298.688 121.521Z" fill="url(#paint19_linear_275_984)"/>
<defs>
<linearGradient id="paint0_linear_275_984" x1="310" y1="0" x2="310" y2="454" gradientUnits="userSpaceOnUse">
<stop stop-color="#d6e2fb"/>
<stop offset="1" stop-color="#d6e2fb" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint1_linear_275_984" x1="441.271" y1="499.484" x2="464.555" y2="559.635" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint2_linear_275_984" x1="439.79" y1="443.5" x2="476.79" y2="526" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint3_linear_275_984" x1="306.73" y1="421.435" x2="196.29" y2="367.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint4_linear_275_984" x1="312.29" y1="450" x2="201.664" y2="568.263" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint5_linear_275_984" x1="427.289" y1="381.5" x2="316.289" y2="464" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint6_linear_275_984" x1="414.289" y1="397" x2="75.7891" y2="490.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint7_linear_275_984" x1="606.726" y1="377.746" x2="461.5" y2="422" gradientUnits="userSpaceOnUse">
<stop stop-color="#d6e2fb"/>
<stop offset="1" stop-color="#d6e2fb" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint8_linear_275_984" x1="277.972" y1="137.409" x2="283.939" y2="170.277" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint9_linear_275_984" x1="399.377" y1="522" x2="399.377" y2="586.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint10_linear_275_984" x1="268.289" y1="214" x2="354.789" y2="367" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint11_linear_275_984" x1="409.788" y1="210" x2="419.788" y2="469" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint12_linear_275_984" x1="397.29" y1="264.5" x2="438.79" y2="266" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint13_linear_275_984" x1="298.789" y1="258.5" x2="397.289" y2="282" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint14_linear_275_984" x1="379.289" y1="266" x2="435.289" y2="266" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint15_linear_275_984" x1="284.039" y1="241" x2="284.039" y2="303" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint16_linear_275_984" x1="317.789" y1="274" x2="307.289" y2="234.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint17_linear_275_984" x1="287.289" y1="117" x2="285.786" y2="134.622" gradientUnits="userSpaceOnUse">
<stop stop-color="#2563eb"/>
<stop offset="1" stop-color="#2563eb" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint18_linear_275_984" x1="307.224" y1="145.272" x2="302.26" y2="133.669" gradientUnits="userSpaceOnUse">
<stop stop-color="#2563eb"/>
<stop offset="1" stop-color="#2563eb" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint19_linear_275_984" x1="302.4" y1="112.985" x2="304.255" y2="134.881" gradientUnits="userSpaceOnUse">
<stop stop-color="#2563eb"/>
<stop offset="1" stop-color="#2563eb" stop-opacity="0"/>
</linearGradient>
</defs>
</svg>
SVG,
        'step1' => <<<'SVG'
<svg class="onboarding-illustration-svg" aria-hidden="true" width="609" height="495" viewBox="0 0 609 495" fill="none" xmlns="http://www.w3.org/2000/svg">
<path fill-rule="evenodd" clip-rule="evenodd" d="M584.052 275.388C584.052 277.731 583.625 279.974 582.846 282.044C582.197 283.766 582.641 285.782 584.047 286.97C590.409 292.342 594.45 300.378 594.45 309.357C594.45 312.534 593.944 315.594 593.008 318.459C592.523 319.946 592.932 321.602 594.094 322.65C602.826 330.517 608.315 341.913 608.315 354.591C608.315 378.328 589.072 397.571 565.334 397.571C541.597 397.571 522.354 378.328 522.354 354.591C522.354 342.031 527.741 330.73 536.33 322.871C537.484 321.815 537.881 320.155 537.384 318.672C536.403 315.745 535.872 312.613 535.872 309.357C535.872 300.378 539.912 292.342 546.274 286.97C547.68 285.782 548.124 283.766 547.476 282.044C546.697 279.974 546.27 277.731 546.27 275.388C546.27 264.955 554.728 256.498 565.161 256.498C575.594 256.498 584.052 264.955 584.052 275.388Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M564.987 274.215C565.54 274.215 565.987 274.663 565.987 275.215L565.987 441.245C565.987 441.798 565.54 442.245 564.987 442.245C564.435 442.245 563.987 441.798 563.987 441.245L563.987 275.215C563.987 274.663 564.435 274.215 564.987 274.215Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M584.633 349.271C585.082 349.592 585.187 350.217 584.866 350.666L565.802 377.356C565.481 377.805 564.856 377.909 564.407 377.588C563.957 377.267 563.853 376.643 564.174 376.193L583.238 349.504C583.559 349.054 584.184 348.95 584.633 349.271Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M545.343 308.023C544.893 308.344 544.789 308.969 545.11 309.418L564.174 336.108C564.495 336.557 565.12 336.661 565.569 336.34C566.018 336.019 566.122 335.395 565.801 334.945L546.737 308.256C546.416 307.806 545.792 307.702 545.343 308.023Z" fill="#9ab7f6"/>
<path d="M538.043 417.336C537.993 416.199 538.902 415.249 540.041 415.249H590.281C591.42 415.249 592.329 416.199 592.279 417.336L588.988 492.365C588.941 493.435 588.061 494.278 586.99 494.278H543.332C542.261 494.278 541.381 493.435 541.334 492.365L538.043 417.336Z" fill="#d6e2fb"/>
<path d="M538.043 417.336C537.993 416.199 538.902 415.249 540.041 415.249H590.281C591.42 415.249 592.329 416.199 592.279 417.336L588.988 492.365C588.941 493.435 588.061 494.278 586.99 494.278H543.332C542.261 494.278 541.381 493.435 541.334 492.365L538.043 417.336Z" fill="url(#paint0_linear_186_1570)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M24.2629 275.388C24.2629 277.731 24.6893 279.974 25.4687 282.044C26.1171 283.766 25.673 285.782 24.2672 286.97C17.905 292.342 13.8644 300.378 13.8644 309.357C13.8644 312.534 14.3703 315.594 15.306 318.459C15.7918 319.946 15.3825 321.602 14.2203 322.65C5.48885 330.517 -0.000366211 341.913 -0.000366211 354.591C-0.000366211 378.328 19.2427 397.571 42.9803 397.571C66.7179 397.571 85.9609 378.328 85.9609 354.591C85.9609 342.031 80.5739 330.73 71.9843 322.871C70.83 321.815 70.4334 320.155 70.9307 318.672C71.9115 315.745 72.4428 312.613 72.4428 309.357C72.4428 300.378 68.4022 292.342 62.04 286.97C60.6342 285.782 60.1901 283.766 60.8385 282.044C61.6179 279.974 62.0443 277.731 62.0443 275.388C62.0443 264.955 53.5867 256.498 43.1536 256.498C32.7205 256.498 24.2629 264.955 24.2629 275.388Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M43.3271 274.215C42.7749 274.215 42.3271 274.663 42.3271 275.215L42.3271 441.245C42.3271 441.798 42.7749 442.245 43.3271 442.245C43.8794 442.245 44.3271 441.798 44.3271 441.245L44.3271 275.215C44.3271 274.663 43.8794 274.215 43.3271 274.215Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M23.6814 349.271C23.232 349.592 23.1279 350.217 23.4489 350.666L42.5129 377.356C42.8339 377.805 43.4584 377.909 43.9079 377.588C44.3573 377.267 44.4614 376.643 44.1404 376.193L25.0764 349.504C24.7553 349.054 24.1308 348.95 23.6814 349.271Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M62.9719 308.023C63.4213 308.344 63.5254 308.969 63.2044 309.418L44.1404 336.108C43.8194 336.557 43.1949 336.661 42.7455 336.34C42.2961 336.019 42.192 335.395 42.513 334.945L61.577 308.256C61.898 307.806 62.5225 307.702 62.9719 308.023Z" fill="#9ab7f6"/>
<path d="M70.3633 415.249H15.9442L19.4104 494.278H66.8971L70.3633 415.249Z" fill="#d6e2fb"/>
<path d="M70.3633 415.249H15.9442L19.4104 494.278H66.8971L70.3633 415.249Z" fill="url(#paint1_linear_186_1570)"/>
<path d="M147.659 6.00001C147.659 2.68631 150.345 0 153.659 0H466.787C470.101 0 472.787 2.68629 472.787 6V310.809C472.787 314.123 470.101 316.809 466.787 316.809H153.659C150.345 316.809 147.659 314.123 147.659 310.809V6.00001Z" fill="#d6e2fb"/>
<path d="M220.103 153.086C220.103 151.429 221.446 150.086 223.103 150.086H397.691C399.347 150.086 400.691 151.429 400.691 153.086V175.508C400.691 177.165 399.347 178.508 397.691 178.508H223.103C221.446 178.508 220.103 177.165 220.103 175.508V153.086Z" fill="white"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M269.322 164.297C269.322 163.531 269.943 162.91 270.709 162.91H350.084C350.85 162.91 351.471 163.531 351.471 164.297C351.471 165.063 350.85 165.683 350.084 165.683H270.709C269.943 165.683 269.322 165.063 269.322 164.297Z" fill="#d6e2fb"/>
<path d="M220.103 197.799C220.103 196.142 221.446 194.799 223.103 194.799H397.691C399.347 194.799 400.691 196.142 400.691 197.799V220.222C400.691 221.879 399.347 223.222 397.691 223.222H223.103C221.446 223.222 220.103 221.879 220.103 220.222V197.799Z" fill="white"/>
<path d="M254.765 209.009C254.765 210.732 253.368 212.129 251.645 212.129C249.922 212.129 248.525 210.732 248.525 209.009C248.525 207.286 249.922 205.89 251.645 205.89C253.368 205.89 254.765 207.286 254.765 209.009Z" fill="#d6e2fb"/>
<path d="M266.55 209.009C266.55 210.732 265.153 212.129 263.43 212.129C261.707 212.129 260.311 210.732 260.311 209.009C260.311 207.286 261.707 205.89 263.43 205.89C265.153 205.89 266.55 207.286 266.55 209.009Z" fill="#d6e2fb"/>
<path d="M278.335 209.009C278.335 210.732 276.938 212.129 275.215 212.129C273.492 212.129 272.096 210.732 272.096 209.009C272.096 207.286 273.492 205.89 275.215 205.89C276.938 205.89 278.335 207.286 278.335 209.009Z" fill="#d6e2fb"/>
<path d="M290.12 209.009C290.12 210.732 288.723 212.129 287 212.129C285.278 212.129 283.881 210.732 283.881 209.009C283.881 207.286 285.278 205.89 287 205.89C288.723 205.89 290.12 207.286 290.12 209.009Z" fill="#d6e2fb"/>
<path d="M301.904 209.009C301.904 210.732 300.507 212.129 298.785 212.129C297.062 212.129 295.665 210.732 295.665 209.009C295.665 207.286 297.062 205.89 298.785 205.89C300.507 205.89 301.904 207.286 301.904 209.009Z" fill="#d6e2fb"/>
<path d="M313.689 209.009C313.689 210.732 312.293 212.129 310.57 212.129C308.847 212.129 307.45 210.732 307.45 209.009C307.45 207.286 308.847 205.89 310.57 205.89C312.293 205.89 313.689 207.286 313.689 209.009Z" fill="#d6e2fb"/>
<path d="M325.474 209.009C325.474 210.732 324.078 212.129 322.355 212.129C320.632 212.129 319.235 210.732 319.235 209.009C319.235 207.286 320.632 205.89 322.355 205.89C324.078 205.89 325.474 207.286 325.474 209.009Z" fill="#d6e2fb"/>
<path d="M337.26 209.009C337.26 210.732 335.863 212.129 334.14 212.129C332.417 212.129 331.021 210.732 331.021 209.009C331.021 207.286 332.417 205.89 334.14 205.89C335.863 205.89 337.26 207.286 337.26 209.009Z" fill="#d6e2fb"/>
<path d="M349.045 209.009C349.045 210.732 347.648 212.129 345.925 212.129C344.202 212.129 342.806 210.732 342.806 209.009C342.806 207.286 344.202 205.89 345.925 205.89C347.648 205.89 349.045 207.286 349.045 209.009Z" fill="#d6e2fb"/>
<path d="M360.83 209.009C360.83 210.732 359.433 212.129 357.71 212.129C355.987 212.129 354.591 210.732 354.591 209.009C354.591 207.286 355.987 205.89 357.71 205.89C359.433 205.89 360.83 207.286 360.83 209.009Z" fill="#d6e2fb"/>
<path d="M372.615 209.009C372.615 210.732 371.218 212.129 369.496 212.129C367.773 212.129 366.376 210.732 366.376 209.009C366.376 207.286 367.773 205.89 369.496 205.89C371.218 205.89 372.615 207.286 372.615 209.009Z" fill="#d6e2fb"/>
<path d="M355.284 78.162C355.284 102.952 335.187 123.049 310.397 123.049C285.606 123.049 265.51 102.952 265.51 78.162C265.51 53.3715 285.606 33.2749 310.397 33.2749C335.187 33.2749 355.284 53.3715 355.284 78.162Z" fill="white"/>
<path d="M310.137 76.3858C313.906 76.3858 316.961 73.3305 316.961 69.5617C316.961 65.7929 313.906 62.7377 310.137 62.7377C306.368 62.7377 303.313 65.7929 303.313 69.5617C303.313 73.3305 306.368 76.3858 310.137 76.3858Z" fill="#d6e2fb"/>
<path d="M306.237 82.2349H314.036C316.105 82.2349 318.088 83.0566 319.551 84.5192C321.014 85.9818 321.835 87.9655 321.835 90.0338V93.9333H298.438V90.0338C298.438 87.9655 299.26 85.9818 300.723 84.5192C302.185 83.0566 304.169 82.2349 306.237 82.2349Z" fill="#d6e2fb"/>
<path d="M254.418 435.699L250.259 478.506H234.314V432.579L254.418 435.699Z" fill="#FDBA8C"/>
<path d="M254.418 435.699L250.259 478.506H234.314V432.579L254.418 435.699Z" fill="url(#paint2_linear_186_1570)"/>
<path d="M253.562 445.057H233.935C232.87 445.057 231.993 444.238 231.945 443.175C231.111 424.639 229.918 366.35 232.036 338.992L269.47 339.859C268.927 358.206 260.123 415.466 255.531 443.404C255.373 444.37 254.541 445.057 253.562 445.057Z" fill="#2563eb"/>
<path d="M253.562 445.057H233.935C232.87 445.057 231.993 444.238 231.945 443.175C231.111 424.639 229.918 366.35 232.036 338.992L269.47 339.859C268.927 358.206 260.123 415.466 255.531 443.404C255.373 444.37 254.541 445.057 253.562 445.057Z" fill="url(#paint3_linear_186_1570)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M249.673 445.058H255.259C256.895 435.156 259.144 421.043 261.371 406.185C260.886 406.177 260.396 406.479 260.318 407.094L259.055 417.06C258.931 418.037 257.644 418.31 257.134 417.468L251.926 408.878C251.293 407.833 249.689 408.547 250.041 409.717L252.941 419.335C253.225 420.278 252.161 421.051 251.352 420.489L243.101 414.76C242.097 414.063 240.922 415.368 241.72 416.293L248.281 423.9C248.924 424.646 248.266 425.785 247.299 425.601L237.431 423.723C236.23 423.495 235.688 425.165 236.793 425.685L245.88 429.967C246.771 430.386 246.634 431.694 245.675 431.919L235.896 434.218C234.707 434.497 234.89 436.243 236.112 436.27L246.155 436.484C247.139 436.506 247.546 437.756 246.762 438.352L238.763 444.429C238.53 444.606 238.403 444.829 238.36 445.058H242.553L249.057 442.327C249.965 441.946 250.845 442.923 250.371 443.787L249.673 445.058Z" fill="#c8d8fa" fill-opacity="0.2"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M231.174 357.009L232.524 359.236C233.035 360.078 234.321 359.805 234.445 358.828L235.709 348.862C235.862 347.65 237.618 347.65 237.772 348.862L239.035 358.828C239.159 359.805 240.445 360.078 240.956 359.236L246.164 350.646C246.797 349.601 248.401 350.315 248.048 351.485L245.149 361.103C244.865 362.046 245.929 362.819 246.738 362.257L254.989 356.528C255.993 355.831 257.168 357.136 256.37 358.061L249.809 365.668C249.166 366.414 249.824 367.553 250.791 367.369L260.659 365.491C261.86 365.263 262.402 366.933 261.297 367.453L252.21 371.734C251.319 372.154 251.456 373.462 252.415 373.687L262.194 375.986C263.383 376.265 263.2 378.011 261.978 378.038L251.935 378.252C250.95 378.273 250.544 379.524 251.328 380.12L259.327 386.197C260.3 386.936 259.422 388.457 258.295 387.984L249.033 384.095C248.125 383.714 247.245 384.691 247.719 385.554L252.555 394.359C253.143 395.43 251.722 396.462 250.885 395.572L244.006 388.253C243.331 387.535 242.13 388.07 242.212 389.051L243.048 399.062C243.15 400.279 241.432 400.644 241.03 399.491L237.722 390.006C237.398 389.076 236.083 389.076 235.758 390.006L232.45 399.491C232.201 400.206 231.445 400.337 230.932 400.043C230.91 397.95 230.893 395.836 230.879 393.711L231.268 389.051C231.302 388.652 231.123 388.327 230.853 388.13C230.823 377.383 230.908 366.582 231.174 357.009Z" fill="#c8d8fa" fill-opacity="0.2"/>
<path d="M197.919 490.118H251.991V492.278C251.991 493.382 251.096 494.278 249.991 494.278H199.919C198.814 494.278 197.919 493.382 197.919 492.278V490.118Z" fill="#2563eb"/>
<path d="M198.004 489.919L197.919 490.118H251.906L251.68 477.775C251.663 476.831 250.715 476.162 249.775 476.254C244.706 476.75 237.602 473.548 236.087 471.166C235.432 470.136 234.608 469.499 233.83 469.121C232.553 468.501 231.138 469.115 229.984 469.943C223.039 474.93 213.356 479.297 206.178 482.117C202.532 483.549 199.536 486.313 198.004 489.919Z" fill="#1F2A37"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M214.502 478.678L214.912 479.59C215.69 481.318 216.455 483.038 217.025 484.615C217.589 486.174 218 487.693 218 489V490H216V489C216 488.054 215.691 486.806 215.145 485.295C214.604 483.801 213.87 482.148 213.088 480.41L212.678 479.498L214.502 478.678Z" fill="#374151"/>
<path d="M202.945 435.699L176.602 478.506H158.578L180.068 432.579L202.945 435.699Z" fill="#FDBA8C"/>
<path d="M202.945 435.699L176.602 478.506H158.578L180.068 432.579L202.945 435.699Z" fill="url(#paint4_linear_186_1570)"/>
<path d="M379.2 384.052C426.895 395.283 450.141 356.381 455.803 335.526C433.792 320.101 298.091 305.37 263.429 301.73C236.056 298.856 192.02 387.167 176.667 434.395C176.331 435.426 176.9 436.5 177.925 436.854L200.27 444.553C201.13 444.85 202.083 444.528 202.587 443.769L266.896 346.965C287.346 354.013 331.505 372.822 379.2 384.052Z" fill="#2563eb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M256.545 362.549L249.101 367.718C248.292 368.279 247.228 367.506 247.512 366.563L251.485 353.383C251.838 352.214 250.234 351.499 249.6 352.544L242.464 364.316C241.953 365.158 240.667 364.884 240.543 363.907L238.812 350.251C238.658 349.039 236.902 349.039 236.749 350.251L235.017 363.907C234.893 364.884 233.607 365.158 233.096 364.316L225.96 352.544C225.327 351.499 223.723 352.214 224.075 353.383L228.048 366.563C228.332 367.506 227.268 368.279 226.459 367.718L215.152 359.866C214.149 359.169 212.974 360.474 213.772 361.4L222.762 371.824C223.405 372.57 222.747 373.709 221.78 373.525L208.257 370.951C207.057 370.723 206.514 372.393 207.619 372.914L220.072 378.78C220.963 379.2 220.826 380.508 219.867 380.733L206.466 383.883C205.277 384.162 205.461 385.909 206.682 385.935L220.445 386.229C221.429 386.25 221.836 387.501 221.051 388.097L210.09 396.424C209.118 397.163 209.996 398.684 211.122 398.211L223.814 392.882C224.723 392.501 225.603 393.478 225.128 394.342L218.502 406.408C217.914 407.479 219.335 408.511 220.171 407.62L229.599 397.59C230.274 396.872 231.475 397.407 231.393 398.389L231.2 400.701L256.545 362.549Z" fill="#c8d8fa" fill-opacity="0.4"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M300.736 305.873L287.351 306.16C286.366 306.181 285.96 307.432 286.744 308.027L297.705 316.355C298.678 317.094 297.8 318.615 296.673 318.142L283.981 312.813C283.073 312.432 282.193 313.409 282.667 314.272L289.293 326.338C289.881 327.409 288.461 328.441 287.624 327.551L278.196 317.521C277.522 316.803 276.32 317.338 276.402 318.319L277.548 332.037C277.65 333.255 275.932 333.62 275.53 332.466L270.997 319.468C270.673 318.538 269.358 318.538 269.033 319.468L264.5 332.466C264.098 333.62 262.381 333.255 262.482 332.037L263.628 318.319C263.71 317.338 262.509 316.803 261.834 317.521L252.406 327.551C251.57 328.441 250.149 327.409 250.737 326.338L257.363 314.272C257.837 313.409 256.957 312.432 256.049 312.813L243.357 318.142C242.23 318.615 241.353 317.094 242.325 316.355L253.286 308.027C254.071 307.432 253.664 306.181 252.68 306.16L250.199 306.107C254.88 302.908 259.341 301.303 263.429 301.732C271.396 302.569 284.701 303.991 300.736 305.873Z" fill="#c8d8fa" fill-opacity="0.4"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M373.153 382.588C373.433 382.17 373.434 381.564 372.922 381.175L361.961 372.847C361.177 372.251 361.583 371 362.568 370.979L376.33 370.685C377.552 370.659 377.735 368.912 376.546 368.633L363.145 365.484C362.187 365.258 362.049 363.95 362.94 363.531L375.393 357.664C376.498 357.143 375.956 355.473 374.755 355.702L361.232 358.275C360.265 358.459 359.607 357.32 360.25 356.574L369.241 346.15C370.039 345.224 368.864 343.92 367.86 344.616L356.553 352.468C355.744 353.029 354.68 352.256 354.964 351.313L358.937 338.133C359.29 336.964 357.686 336.249 357.052 337.294L349.916 349.066C349.405 349.908 348.119 349.635 347.995 348.658L346.264 335.001C346.11 333.789 344.354 333.789 344.2 335.001L342.469 348.658C342.345 349.635 341.059 349.908 340.548 349.066L333.412 337.294C332.779 336.249 331.175 336.964 331.527 338.133L335.5 351.313C335.784 352.256 334.72 353.029 333.911 352.468L322.604 344.616C321.6 343.92 320.426 345.224 321.224 346.15L330.214 356.574C330.857 357.32 330.199 358.459 329.232 358.275L315.709 355.702C314.509 355.473 313.966 357.143 315.071 357.664L327.524 363.531C328.415 363.95 328.278 365.258 327.319 365.484L321.257 366.908C332.626 370.845 344.937 374.839 357.758 378.475C357.831 377.84 358.506 377.342 359.198 377.633L367.625 381.171C369.46 381.652 371.303 382.125 373.153 382.588Z" fill="#c8d8fa" fill-opacity="0.4"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M435.843 327.79C435.91 327.916 436.007 328.033 436.137 328.131L447.098 336.459C448.071 337.198 447.193 338.719 446.066 338.246L433.374 332.917C432.466 332.536 431.586 333.513 432.06 334.376L438.686 346.442C439.274 347.513 437.854 348.545 437.017 347.655L427.589 337.625C426.915 336.907 425.713 337.442 425.795 338.423L426.941 352.141C427.043 353.359 425.325 353.724 424.923 352.57L420.39 339.572C420.066 338.642 418.751 338.642 418.426 339.572L413.893 352.57C413.491 353.724 411.774 353.359 411.875 352.141L413.021 338.423C413.103 337.442 411.902 336.907 411.227 337.625L401.799 347.655C400.963 348.545 399.542 347.513 400.13 346.442L406.756 334.376C407.231 333.513 406.351 332.536 405.442 332.917L392.75 338.246C391.623 338.719 390.746 337.198 391.718 336.459L402.679 328.131C403.464 327.536 403.057 326.285 402.073 326.264L388.31 325.969C387.089 325.943 386.905 324.197 388.094 323.918L401.495 320.768C401.752 320.708 401.95 320.57 402.085 320.392C414.89 322.751 426.505 325.234 435.843 327.79Z" fill="#c8d8fa" fill-opacity="0.4"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M188.098 440.361L187.001 438.362C186.527 437.499 187.407 436.522 188.315 436.903L201.007 442.232C202.134 442.705 203.012 441.184 202.039 440.445L191.078 432.117C190.293 431.522 190.7 430.271 191.684 430.25L205.447 429.955C206.669 429.929 206.852 428.183 205.663 427.903L192.262 424.754C191.303 424.529 191.166 423.221 192.057 422.801L204.51 416.934C205.615 416.414 205.072 414.744 203.872 414.972L190.349 417.545C189.382 417.73 188.724 416.591 189.367 415.845L198.357 405.42C199.155 404.495 197.981 403.19 196.977 403.887L185.67 411.738C185.427 411.907 185.161 411.955 184.916 411.915C181.37 420.727 178.367 428.998 176.082 436.221L188.098 440.361Z" fill="#c8d8fa" fill-opacity="0.4"/>
<path d="M126.169 490.118H179.202V492.278C179.202 493.382 178.306 494.278 177.202 494.278H128.169C127.064 494.278 126.169 493.382 126.169 492.278V490.118Z" fill="#2563eb"/>
<path d="M126.254 489.918L126.169 490.118H179.116L179.782 477.927C179.836 476.942 178.852 476.191 177.866 476.242C173.084 476.49 167.216 472.82 165.724 470.473C165.068 469.443 164.244 468.806 163.466 468.428C162.19 467.807 160.774 468.422 159.62 469.247C152.502 474.337 141.959 479.113 134.421 482.102C130.779 483.546 127.786 486.313 126.254 489.918Z" fill="#1F2A37"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M143.002 478.678L143.412 479.59C144.19 481.318 144.955 483.038 145.525 484.615C146.089 486.174 146.5 487.693 146.5 489V490H144.5V489C144.5 488.054 144.191 486.806 143.645 485.295C143.104 483.801 142.37 482.148 141.588 480.41L141.178 479.498L143.002 478.678Z" fill="#374151"/>
<path d="M236.452 248.609C235.902 247.242 236.908 245.752 238.382 245.752H299.737C301.434 245.752 302.961 246.783 303.595 248.358L323.343 297.398H256.099L236.452 248.609Z" fill="white"/>
<path d="M254.224 257.87C253.96 257.213 254.444 256.497 255.152 256.497H291.503C292.319 256.497 293.054 256.994 293.358 257.752L304.421 285.28C304.685 285.937 304.201 286.653 303.493 286.653H267.143C266.326 286.653 265.591 286.157 265.287 285.399L254.224 257.87Z" fill="#d6e2fb"/>
<path d="M265.554 264.374C265.289 263.716 265.773 263 266.481 263H285.8C286.616 263 287.35 263.496 287.655 264.252L293.447 278.626C293.712 279.283 293.228 280 292.519 280H273.201C272.385 280 271.651 279.504 271.346 278.747L265.554 264.374Z" fill="white"/>
<path d="M318.837 308.143H386.08L323.343 297.398H256.099L318.837 308.143Z" fill="#d6e2fb"/>
<path d="M318.837 308.143H386.081L387.121 311.263H319.877L318.837 308.143Z" fill="#111928"/>
<path d="M318.837 308.143L256.099 297.398L257.138 299.651L319.876 311.263L318.837 308.143Z" fill="#111928"/>
<path d="M497.314 151.736C500.809 157.113 498.177 165.024 491.435 169.405C484.694 173.787 476.396 172.98 472.901 167.603C469.406 162.226 472.038 154.315 478.78 149.933C485.521 145.552 493.819 146.359 497.314 151.736Z" fill="#111928"/>
<path d="M470.246 189.977C460.811 190.729 455.311 179.964 453.741 174.488C451.604 176.818 451.179 183.593 451.234 186.689C445.757 171.971 457.342 155.248 478.861 170.24C500.379 185.233 490.664 197.422 506.675 232.857C515.457 252.293 496.5 258.933 493 257.933C494.107 234.482 484.896 226.264 480.726 222.544L480.666 222.49C476.525 218.796 470.423 204.536 474.259 200.196C478.095 195.856 477.907 188.506 474.77 187.63C472.262 186.929 470.709 188.903 470.246 189.977Z" fill="#111928"/>
<path d="M465.727 206.912L468.646 224.171C474.143 225.913 478.455 223.626 479.924 222.264C474.731 214.002 474.098 204.442 474.43 200.167C476.191 198.112 479.355 191.46 476.34 188.595C473.324 185.73 470.982 188.095 470.188 189.635C461.185 189.611 455.472 179.527 453.741 174.488C452.535 174.634 450.406 178.884 451.538 194.723C452.67 210.562 461.469 209.448 465.727 206.912Z" fill="#FDBA8C"/>
<path d="M465.727 206.912L468.646 224.171C474.143 225.913 478.455 223.626 479.924 222.264C474.731 214.002 474.098 204.442 474.43 200.167C476.191 198.112 479.355 191.46 476.34 188.595C473.324 185.73 470.982 188.095 470.188 189.635C461.185 189.611 455.472 179.527 453.741 174.488C452.535 174.634 450.406 178.884 451.538 194.723C452.67 210.562 461.469 209.448 465.727 206.912Z" fill="url(#paint5_linear_186_1570)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M474.374 200.084C472.251 202.83 467.42 205.996 465.728 206.915L465.728 206.919L468.216 221.632C472.26 222.189 475.984 221.062 478.538 219.804C474.915 212.598 474.222 204.342 474.374 200.084Z" fill="url(#paint6_linear_186_1570)"/>
<path d="M457.981 191.304C458.077 191.87 457.695 192.407 457.129 192.502C456.563 192.598 456.026 192.217 455.93 191.651C455.834 191.084 456.216 190.548 456.782 190.452C457.348 190.356 457.885 190.738 457.981 191.304Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M456.159 187.616C455.436 187.747 454.899 188.153 454.415 188.678C454.228 188.881 453.912 188.894 453.709 188.707C453.506 188.52 453.493 188.204 453.68 188.001C454.239 187.394 454.958 186.818 455.98 186.632C456.997 186.447 458.229 186.663 459.788 187.455C460.034 187.58 460.132 187.881 460.007 188.127C459.882 188.373 459.581 188.471 459.335 188.346C457.892 187.613 456.888 187.483 456.159 187.616Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M474.767 192.749C474.808 192.015 474.54 191.398 474.142 190.805C473.989 190.576 474.05 190.265 474.279 190.111C474.509 189.958 474.819 190.019 474.973 190.249C475.432 190.934 475.824 191.768 475.765 192.805C475.707 193.838 475.208 194.984 474.074 196.315C473.895 196.525 473.579 196.55 473.369 196.37C473.159 196.191 473.134 195.876 473.313 195.666C474.363 194.434 474.725 193.488 474.767 192.749Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M452.862 190.765C453.135 190.806 453.324 191.06 453.283 191.334L452.265 198.137C452.247 198.258 452.345 198.366 452.468 198.358L455.409 198.181C455.685 198.164 455.922 198.374 455.939 198.649C455.955 198.925 455.745 199.162 455.47 199.179L452.528 199.357C451.771 199.402 451.164 198.739 451.276 197.989L452.294 191.186C452.335 190.912 452.589 190.724 452.862 190.765Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M457.464 202.018C458.165 201.613 458.654 200.938 458.973 200.061C459.067 199.801 459.354 199.667 459.613 199.762C459.873 199.856 460.007 200.143 459.912 200.402C459.538 201.432 458.924 202.329 457.964 202.884C457.003 203.439 455.765 203.612 454.221 203.327C453.949 203.277 453.77 203.016 453.82 202.745C453.87 202.473 454.131 202.294 454.402 202.344C455.783 202.599 456.764 202.423 457.464 202.018Z" fill="#111928"/>
<path d="M298.213 303.291C304.452 306.202 319.703 308.317 329.582 309.876L332.355 295.145C316.179 296.647 291.974 300.379 298.213 303.291Z" fill="#FDBA8C"/>
<path d="M298.213 303.291C304.452 306.202 319.703 308.317 329.582 309.876L332.355 295.145C316.179 296.647 291.974 300.379 298.213 303.291Z" fill="url(#paint7_linear_186_1570)"/>
<path d="M477.64 216.35C473.236 217.645 469.396 217.327 467.591 216.95C467.399 216.91 467.207 217.03 467.167 217.222L466.61 219.867C466.574 220.037 466.668 220.207 466.832 220.261C471.142 221.702 476.995 221.236 479.199 220.163L477.64 216.35Z" fill="#2563eb"/>
<path d="M456.238 336L408.049 320.855C407.529 315.307 410.927 296.057 428.677 263.437C446.427 230.818 461.727 220.887 467.158 220H479.292C514.376 239.572 478.541 305.488 456.238 336Z" fill="#F9FAFB"/>
<path d="M456.238 336L408.049 320.855C407.529 315.307 410.927 296.057 428.677 263.437C446.427 230.818 461.727 220.887 467.158 220H479.292C514.376 239.572 478.541 305.488 456.238 336Z" fill="url(#paint8_linear_186_1570)"/>
<path d="M328.195 312.956L329.928 293.932C351.5 293.932 380.72 294.506 402.73 294.506C418.559 272.553 445 234.5 456.5 226.5C463.5 221.63 480.813 231.982 477 245.5C473.187 259.018 437.085 327.9 414.902 330.327C397.155 332.268 353.036 319.888 328.195 312.956Z" fill="#F9FAFB"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M429.214 322.176C424.684 315.585 417.092 311.263 408.49 311.263C397.728 311.263 388.546 318.028 384.965 327.537C385.431 327.619 385.895 327.7 386.357 327.779C389.806 318.925 398.415 312.65 408.49 312.65C416.678 312.65 423.898 316.795 428.166 323.1C428.514 322.8 428.863 322.492 429.214 322.176Z" fill="#9ab7f6"/>
<path d="M472.787 441.938L472.787 447.83L289.079 447.83L289.079 441.938L472.787 441.938Z" fill="#d6e2fb"/>
<path d="M472.787 308.143H478.68V492.277C478.68 493.382 477.784 494.277 476.68 494.277H474.787C473.683 494.277 472.787 493.382 472.787 492.277V308.143Z" fill="#d6e2fb"/>
<path d="M442.631 395.145H282.62C280.445 395.145 278.681 393.381 278.681 391.205C278.681 389.436 279.857 387.882 281.569 387.44C297.01 383.459 331.065 376.774 364.815 376.774C400.864 376.774 431.828 384.4 442.804 388.212L442.631 395.145Z" fill="#D6DBE2"/>
<path d="M504.587 262.278L442.632 395.144L436.701 392.117C438.537 379.136 445.694 345.69 459.629 315.749C472.513 288.07 489.624 266.75 498.22 257.755C499.367 256.555 501.145 256.264 502.651 256.965C504.653 257.897 505.52 260.276 504.587 262.278Z" fill="#D6DBE2"/>
<path d="M283.533 395.144H289.426V492.277C289.426 493.382 288.53 494.277 287.426 494.277H285.533C284.429 494.277 283.533 493.382 283.533 492.277V395.144Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M324.236 375.299C306.206 398.289 294.594 426.237 289.504 445.799L283.802 444.316C289.053 424.132 300.976 395.409 319.599 371.663C338.215 347.924 363.863 328.767 396.531 328.767H478.16V334.66H396.531C366.392 334.66 342.271 352.302 324.236 375.299Z" fill="#d6e2fb"/>
<defs>
<linearGradient id="paint0_linear_186_1570" x1="657.673" y1="468.867" x2="500.117" y2="468.867" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint1_linear_186_1570" x1="-49.3586" y1="468.867" x2="108.197" y2="468.867" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint2_linear_186_1570" x1="273.656" y1="471.747" x2="238.994" y2="471.747" gradientUnits="userSpaceOnUse">
<stop stop-color="#B43403"/>
<stop offset="1" stop-color="#B43403" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint3_linear_186_1570" x1="189.613" y1="392.057" x2="275.414" y2="458.596" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint4_linear_186_1570" x1="196.706" y1="470.361" x2="173.136" y2="457.536" gradientUnits="userSpaceOnUse">
<stop stop-color="#B43403"/>
<stop offset="1" stop-color="#B43403" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint5_linear_186_1570" x1="506.597" y1="211.329" x2="461.689" y2="218.923" gradientUnits="userSpaceOnUse">
<stop stop-color="#B43403"/>
<stop offset="1" stop-color="#B43403" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint6_linear_186_1570" x1="465.693" y1="175.279" x2="473.524" y2="221.588" gradientUnits="userSpaceOnUse">
<stop stop-color="#B43403"/>
<stop offset="1" stop-color="#B43403" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint7_linear_186_1570" x1="322.476" y1="314.036" x2="320.743" y2="299.478" gradientUnits="userSpaceOnUse">
<stop stop-color="#B43403"/>
<stop offset="1" stop-color="#B43403" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint8_linear_186_1570" x1="444.797" y1="338.996" x2="472.932" y2="269.441" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
</defs>
</svg>

SVG,
        'step2' => <<<'SVG'
<svg class="w-auto max-w-[16rem] h-40 text-gray-800 dark:text-white" aria-hidden="true" width="749" height="699" viewBox="0 0 749 699" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M86.7031 51.9092H671.935V69.5705H86.7031V51.9092Z" fill="#d6e2fb"/>
<path d="M671.935 69.5706H86.7031V501.47H671.935V69.5706Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M86.7031 69.5706H671.935V70.3733H86.7031V69.5706Z" fill="#c8d8fa"/>
<path d="M103.772 59.8736C103.772 61.7587 102.244 63.2869 100.359 63.2869C98.4735 63.2869 96.9453 61.7587 96.9453 59.8736C96.9453 57.9884 98.4735 56.4602 100.359 56.4602C102.244 56.4602 103.772 57.9884 103.772 59.8736Z" fill="#F9FAFB"/>
<path d="M116.284 59.8736C116.284 61.7587 114.756 63.2869 112.87 63.2869C110.985 63.2869 109.457 61.7587 109.457 59.8736C109.457 57.9884 110.985 56.4602 112.87 56.4602C114.756 56.4602 116.284 57.9884 116.284 59.8736Z" fill="#F9FAFB"/>
<path d="M128.801 59.8736C128.801 61.7587 127.273 63.2869 125.388 63.2869C123.503 63.2869 121.975 61.7587 121.975 59.8736C121.975 57.9884 123.503 56.4602 125.388 56.4602C127.273 56.4602 128.801 57.9884 128.801 59.8736Z" fill="#F9FAFB"/>
<path d="M111.592 417.177C111.592 416.291 112.311 415.572 113.197 415.572H645.445C646.332 415.572 647.051 416.291 647.051 417.177V478.189C647.051 479.076 646.332 479.795 645.445 479.795H113.197C112.311 479.795 111.592 479.076 111.592 478.189V417.177Z" fill="#F9FAFB"/>
<path d="M124.436 434.036C124.436 433.149 125.154 432.43 126.041 432.43H153.336C154.223 432.43 154.941 433.149 154.941 434.036V461.33C154.941 462.217 154.223 462.936 153.336 462.936H126.041C125.154 462.936 124.436 462.217 124.436 461.33V434.036Z" fill="#c8d8fa"/>
<path d="M170.195 447.683C170.195 446.353 171.274 445.275 172.604 445.275H414.242C415.573 445.275 416.651 446.353 416.651 447.683C416.651 449.013 415.573 450.092 414.242 450.092H172.604C171.274 450.092 170.195 449.013 170.195 447.683Z" fill="#c8d8fa"/>
<path d="M445.551 447.683C445.551 446.353 446.629 445.275 447.959 445.275H628.586C629.916 445.275 630.995 446.353 630.995 447.683C630.995 449.013 629.916 450.092 628.586 450.092H447.959C446.629 450.092 445.551 449.013 445.551 447.683Z" fill="#c8d8fa"/>
<path d="M111.592 336.899C111.592 336.012 112.311 335.293 113.197 335.293H645.445C646.332 335.293 647.051 336.012 647.051 336.899V397.911C647.051 398.797 646.332 399.516 645.445 399.516H113.197C112.311 399.516 111.592 398.797 111.592 397.911V336.899Z" fill="#F9FAFB"/>
<path d="M124.436 353.757C124.436 352.871 125.154 352.152 126.041 352.152H153.336C154.223 352.152 154.941 352.871 154.941 353.757V381.052C154.941 381.939 154.223 382.658 153.336 382.658H126.041C125.154 382.658 124.436 381.939 124.436 381.052V353.757Z" fill="#c8d8fa"/>
<path d="M170.195 367.405C170.195 366.075 171.274 364.996 172.604 364.996H414.242C415.573 364.996 416.651 366.075 416.651 367.405C416.651 368.735 415.573 369.813 414.242 369.813H172.604C171.274 369.813 170.195 368.735 170.195 367.405Z" fill="#c8d8fa"/>
<path d="M445.551 367.405C445.551 366.075 446.629 364.996 447.959 364.996H628.586C629.916 364.996 630.995 366.075 630.995 367.405C630.995 368.735 629.916 369.813 628.586 369.813H447.959C446.629 369.813 445.551 368.735 445.551 367.405Z" fill="#c8d8fa"/>
<path d="M111.592 256.62C111.592 255.733 112.311 255.014 113.197 255.014H645.445C646.332 255.014 647.051 255.733 647.051 256.62V317.632C647.051 318.518 646.332 319.237 645.445 319.237H113.197C112.311 319.237 111.592 318.518 111.592 317.632V256.62Z" fill="#F9FAFB"/>
<path d="M124.436 273.478C124.436 272.591 125.154 271.873 126.041 271.873H153.336C154.223 271.873 154.941 272.591 154.941 273.478V300.773C154.941 301.66 154.223 302.378 153.336 302.378H126.041C125.154 302.378 124.436 301.66 124.436 300.773V273.478Z" fill="#c8d8fa"/>
<path d="M170.195 287.126C170.195 285.796 171.274 284.717 172.604 284.717H414.242C415.573 284.717 416.651 285.796 416.651 287.126C416.651 288.456 415.573 289.534 414.242 289.534H172.604C171.274 289.534 170.195 288.456 170.195 287.126Z" fill="#c8d8fa"/>
<path d="M445.551 287.126C445.551 285.796 446.629 284.717 447.959 284.717H628.586C629.916 284.717 630.995 285.796 630.995 287.126C630.995 288.456 629.916 289.534 628.586 289.534H447.959C446.629 289.534 445.551 288.456 445.551 287.126Z" fill="#c8d8fa"/>
<path d="M111.592 176.341C111.592 175.454 112.311 174.736 113.197 174.736H645.445C646.332 174.736 647.051 175.454 647.051 176.341V237.353C647.051 238.24 646.332 238.959 645.445 238.959H113.197C112.311 238.959 111.592 238.24 111.592 237.353V176.341Z" fill="#F9FAFB"/>
<path d="M124.436 193.2C124.436 192.313 125.154 191.594 126.041 191.594H153.336C154.223 191.594 154.941 192.313 154.941 193.2V220.495C154.941 221.381 154.223 222.1 153.336 222.1H126.041C125.154 222.1 124.436 221.381 124.436 220.495V193.2Z" fill="#c8d8fa"/>
<path d="M170.195 206.847C170.195 205.517 171.274 204.439 172.604 204.439H414.242C415.573 204.439 416.651 205.517 416.651 206.847C416.651 208.177 415.573 209.255 414.242 209.255H172.604C171.274 209.255 170.195 208.177 170.195 206.847Z" fill="#c8d8fa"/>
<path d="M445.551 206.847C445.551 205.517 446.629 204.439 447.959 204.439H628.586C629.916 204.439 630.995 205.517 630.995 206.847C630.995 208.177 629.916 209.255 628.586 209.255H447.959C446.629 209.255 445.551 208.177 445.551 206.847Z" fill="#c8d8fa"/>
<path d="M111.592 96.0626C111.592 95.1759 112.311 94.457 113.197 94.457H645.445C646.332 94.457 647.051 95.1759 647.051 96.0626V157.074C647.051 157.961 646.332 158.68 645.445 158.68H113.197C112.311 158.68 111.592 157.961 111.592 157.074V96.0626Z" fill="#F9FAFB"/>
<path d="M124.436 112.921C124.436 112.034 125.154 111.315 126.041 111.315H153.336C154.223 111.315 154.941 112.034 154.941 112.921V140.216C154.941 141.102 154.223 141.821 153.336 141.821H126.041C125.154 141.821 124.436 141.102 124.436 140.216V112.921Z" fill="#c8d8fa"/>
<path d="M170.195 126.569C170.195 125.238 171.274 124.16 172.604 124.16H414.242C415.573 124.16 416.651 125.238 416.651 126.569C416.651 127.899 415.573 128.977 414.242 128.977H172.604C171.274 128.977 170.195 127.899 170.195 126.569Z" fill="#c8d8fa"/>
<path d="M445.551 126.569C445.551 125.238 446.629 124.16 447.959 124.16H628.586C629.916 124.16 630.995 125.238 630.995 126.569C630.995 127.899 629.916 128.977 628.586 128.977H447.959C446.629 128.977 445.551 127.899 445.551 126.569Z" fill="#c8d8fa"/>
<path d="M504.953 279.097C504.953 278.211 505.672 277.492 506.559 277.492H747.395C748.281 277.492 749 278.211 749 279.097V289.533H504.953V279.097Z" fill="#c8d8fa"/>
<path d="M749 289.533H504.953V411.101C504.953 411.988 505.672 412.707 506.559 412.707H747.395C748.281 412.707 749 411.988 749 411.101V289.533Z" fill="#c8d8fa"/>
<path d="M749 289.533H504.953V411.101C504.953 411.988 505.672 412.707 506.559 412.707H747.395C748.281 412.707 749 411.988 749 411.101V289.533Z" fill="url(#paint0_linear_411_1552)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M504.953 289.533H749V290.336H504.953V289.533Z" fill="#d6e2fb"/>
<path d="M516.691 282.969C516.691 284.266 515.64 285.317 514.344 285.317C513.047 285.317 511.996 284.266 511.996 282.969C511.996 281.673 513.047 280.622 514.344 280.622C515.64 280.622 516.691 281.673 516.691 282.969Z" fill="#F9FAFB"/>
<path d="M525.299 282.969C525.299 284.266 524.248 285.317 522.951 285.317C521.655 285.317 520.604 284.266 520.604 282.969C520.604 281.673 521.655 280.622 522.951 280.622C524.248 280.622 525.299 281.673 525.299 282.969Z" fill="#F9FAFB"/>
<path d="M533.908 282.969C533.908 284.266 532.857 285.317 531.561 285.317C530.264 285.317 529.213 284.266 529.213 282.969C529.213 281.673 530.264 280.622 531.561 280.622C532.857 280.622 533.908 281.673 533.908 282.969Z" fill="#F9FAFB"/>
<path d="M516.691 307.937C516.691 309.234 515.64 310.285 514.344 310.285C513.047 310.285 511.996 309.234 511.996 307.937C511.996 306.64 513.047 305.589 514.344 305.589C515.64 305.589 516.691 306.64 516.691 307.937Z" fill="#d6e2fb"/>
<path d="M525.023 307.998C525.023 306.668 526.102 305.59 527.432 305.59H579.613C580.943 305.59 582.021 306.668 582.021 307.998C582.021 309.328 580.943 310.406 579.613 310.406H527.432C526.102 310.406 525.023 309.328 525.023 307.998Z" fill="#d6e2fb"/>
<path d="M590.049 307.998C590.049 306.668 591.127 305.59 592.457 305.59H669.525C670.855 305.59 671.933 306.668 671.933 307.998C671.933 309.328 670.855 310.406 669.525 310.406H592.457C591.127 310.406 590.049 309.328 590.049 307.998Z" fill="#d6e2fb"/>
<path d="M516.691 323.993C516.691 325.289 515.64 326.341 514.344 326.341C513.047 326.341 511.996 325.289 511.996 323.993C511.996 322.696 513.047 321.645 514.344 321.645C515.64 321.645 516.691 322.696 516.691 323.993Z" fill="#d6e2fb"/>
<path d="M541.078 324.054C541.078 322.724 542.156 321.645 543.486 321.645H595.668C596.998 321.645 598.076 322.724 598.076 324.054C598.076 325.384 596.998 326.462 595.668 326.462H543.486C542.156 326.462 541.078 325.384 541.078 324.054Z" fill="#d6e2fb"/>
<path d="M606.104 324.054C606.104 322.724 607.182 321.645 608.512 321.645H643.834C645.165 321.645 646.243 322.724 646.243 324.054C646.243 325.384 645.165 326.462 643.834 326.462H608.512C607.182 326.462 606.104 325.384 606.104 324.054Z" fill="#d6e2fb"/>
<path d="M516.691 340.049C516.691 341.345 515.64 342.396 514.344 342.396C513.047 342.396 511.996 341.345 511.996 340.049C511.996 338.752 513.047 337.701 514.344 337.701C515.64 337.701 516.691 338.752 516.691 340.049Z" fill="#d6e2fb"/>
<path d="M525.023 340.109C525.023 338.779 526.102 337.701 527.432 337.701H579.613C580.943 337.701 582.021 338.779 582.021 340.109C582.021 341.439 580.943 342.518 579.613 342.518H527.432C526.102 342.518 525.023 341.439 525.023 340.109Z" fill="#d6e2fb"/>
<path d="M590.049 340.109C590.049 338.779 591.127 337.701 592.457 337.701H669.525C670.855 337.701 671.933 338.779 671.933 340.109C671.933 341.439 670.855 342.518 669.525 342.518H592.457C591.127 342.518 590.049 341.439 590.049 340.109Z" fill="#d6e2fb"/>
<path d="M516.691 356.104C516.691 357.401 515.64 358.452 514.344 358.452C513.047 358.452 511.996 357.401 511.996 356.104C511.996 354.808 513.047 353.757 514.344 353.757C515.64 353.757 516.691 354.808 516.691 356.104Z" fill="#d6e2fb"/>
<path d="M541.078 356.165C541.078 354.835 542.156 353.757 543.486 353.757H625.371C626.701 353.757 627.779 354.835 627.779 356.165C627.779 357.495 626.701 358.573 625.371 358.573H543.486C542.156 358.573 541.078 357.495 541.078 356.165Z" fill="#d6e2fb"/>
<path d="M516.691 372.16C516.691 373.457 515.64 374.508 514.344 374.508C513.047 374.508 511.996 373.457 511.996 372.16C511.996 370.864 513.047 369.812 514.344 369.812C515.64 369.812 516.691 370.864 516.691 372.16Z" fill="#d6e2fb"/>
<path d="M541.078 372.221C541.078 370.891 542.156 369.812 543.486 369.812H621.357C622.687 369.812 623.765 370.891 623.765 372.221C623.765 373.551 622.687 374.629 621.357 374.629H543.486C542.156 374.629 541.078 373.551 541.078 372.221Z" fill="#d6e2fb"/>
<path d="M631.793 372.221C631.793 370.891 632.871 369.812 634.201 369.812H645.44C646.77 369.812 647.849 370.891 647.849 372.221C647.849 373.551 646.77 374.629 645.44 374.629H634.201C632.871 374.629 631.793 373.551 631.793 372.221Z" fill="#d6e2fb"/>
<path d="M516.691 388.216C516.691 389.512 515.64 390.563 514.344 390.563C513.047 390.563 511.996 389.512 511.996 388.216C511.996 386.919 513.047 385.868 514.344 385.868C515.64 385.868 516.691 386.919 516.691 388.216Z" fill="#d6e2fb"/>
<path d="M525.023 388.277C525.023 386.946 526.102 385.868 527.432 385.868H579.613C580.943 385.868 582.021 386.946 582.021 388.277C582.021 389.607 580.943 390.685 579.613 390.685H527.432C526.102 390.685 525.023 389.607 525.023 388.277Z" fill="#d6e2fb"/>
<path d="M590.049 388.277C590.049 386.946 591.127 385.868 592.457 385.868H669.525C670.855 385.868 671.933 386.946 671.933 388.277C671.933 389.607 670.855 390.685 669.525 390.685H592.457C591.127 390.685 590.049 389.607 590.049 388.277Z" fill="#d6e2fb"/>
<path d="M468.824 2.13585C468.824 1.24911 469.543 0.530273 470.43 0.530273H711.266C712.153 0.530273 712.871 1.24911 712.871 2.13585V12.5721H468.824V2.13585Z" fill="#c8d8fa"/>
<path d="M712.871 12.572H468.824V134.14C468.824 135.026 469.543 135.745 470.43 135.745H711.266C712.153 135.745 712.871 135.026 712.871 134.14V12.572Z" fill="#c8d8fa"/>
<path d="M712.871 12.572H468.824V134.14C468.824 135.026 469.543 135.745 470.43 135.745H711.266C712.153 135.745 712.871 135.026 712.871 134.14V12.572Z" fill="url(#paint1_linear_411_1552)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M468.824 12.572H712.871V13.3748H468.824V12.572Z" fill="#d6e2fb"/>
<path d="M480.562 6.00805C480.562 7.30461 479.511 8.35569 478.215 8.35569C476.918 8.35569 475.867 7.30461 475.867 6.00805C475.867 4.71148 476.918 3.6604 478.215 3.6604C479.511 3.6604 480.562 4.71148 480.562 6.00805Z" fill="#F9FAFB"/>
<path d="M489.17 6.00805C489.17 7.30461 488.119 8.35569 486.822 8.35569C485.526 8.35569 484.475 7.30461 484.475 6.00805C484.475 4.71148 485.526 3.6604 486.822 3.6604C488.119 3.6604 489.17 4.71148 489.17 6.00805Z" fill="#F9FAFB"/>
<path d="M497.779 6.00805C497.779 7.30461 496.728 8.35569 495.432 8.35569C494.135 8.35569 493.084 7.30461 493.084 6.00805C493.084 4.71148 494.135 3.6604 495.432 3.6604C496.728 3.6604 497.779 4.71148 497.779 6.00805Z" fill="#F9FAFB"/>
<path d="M480.562 30.9758C480.562 32.2724 479.511 33.3235 478.215 33.3235C476.918 33.3235 475.867 32.2724 475.867 30.9758C475.867 29.6793 476.918 28.6282 478.215 28.6282C479.511 28.6282 480.562 29.6793 480.562 30.9758Z" fill="#d6e2fb"/>
<path d="M488.895 31.0365C488.895 29.7064 489.973 28.6282 491.303 28.6282H543.484C544.814 28.6282 545.892 29.7064 545.892 31.0365C545.892 32.3666 544.814 33.4449 543.484 33.4449H491.303C489.973 33.4449 488.895 32.3666 488.895 31.0365Z" fill="#d6e2fb"/>
<path d="M553.92 31.0365C553.92 29.7064 554.998 28.6282 556.328 28.6282H633.396C634.726 28.6282 635.804 29.7064 635.804 31.0365C635.804 32.3666 634.726 33.4449 633.396 33.4449H556.328C554.998 33.4449 553.92 32.3666 553.92 31.0365Z" fill="#d6e2fb"/>
<path d="M480.562 47.0315C480.562 48.3281 479.511 49.3791 478.215 49.3791C476.918 49.3791 475.867 48.3281 475.867 47.0315C475.867 45.7349 476.918 44.6838 478.215 44.6838C479.511 44.6838 480.562 45.7349 480.562 47.0315Z" fill="#d6e2fb"/>
<path d="M504.949 47.0922C504.949 45.7621 506.027 44.6838 507.358 44.6838H559.539C560.869 44.6838 561.947 45.7621 561.947 47.0922C561.947 48.4223 560.869 49.5006 559.539 49.5006H507.358C506.027 49.5006 504.949 48.4223 504.949 47.0922Z" fill="#d6e2fb"/>
<path d="M569.975 47.0922C569.975 45.7621 571.053 44.6838 572.383 44.6838H607.706C609.036 44.6838 610.114 45.7621 610.114 47.0922C610.114 48.4223 609.036 49.5006 607.706 49.5006H572.383C571.053 49.5006 569.975 48.4223 569.975 47.0922Z" fill="#d6e2fb"/>
<path d="M480.562 63.0874C480.562 64.384 479.511 65.435 478.215 65.435C476.918 65.435 475.867 64.384 475.867 63.0874C475.867 61.7908 476.918 60.7397 478.215 60.7397C479.511 60.7397 480.562 61.7908 480.562 63.0874Z" fill="#d6e2fb"/>
<path d="M488.895 63.1481C488.895 61.818 489.973 60.7397 491.303 60.7397H543.484C544.814 60.7397 545.892 61.818 545.892 63.1481C545.892 64.4782 544.814 65.5565 543.484 65.5565H491.303C489.973 65.5565 488.895 64.4782 488.895 63.1481Z" fill="#d6e2fb"/>
<path d="M553.92 63.1481C553.92 61.818 554.998 60.7397 556.328 60.7397H633.396C634.726 60.7397 635.804 61.818 635.804 63.1481C635.804 64.4782 634.726 65.5565 633.396 65.5565H556.328C554.998 65.5565 553.92 64.4782 553.92 63.1481Z" fill="#d6e2fb"/>
<path d="M480.562 79.1431C480.562 80.4396 479.511 81.4907 478.215 81.4907C476.918 81.4907 475.867 80.4396 475.867 79.1431C475.867 77.8465 476.918 76.7954 478.215 76.7954C479.511 76.7954 480.562 77.8465 480.562 79.1431Z" fill="#d6e2fb"/>
<path d="M504.949 79.2038C504.949 77.8737 506.027 76.7954 507.358 76.7954H589.242C590.572 76.7954 591.65 77.8737 591.65 79.2038C591.65 80.5339 590.572 81.6121 589.242 81.6121H507.358C506.027 81.6121 504.949 80.5339 504.949 79.2038Z" fill="#d6e2fb"/>
<path d="M480.562 95.1987C480.562 96.4953 479.511 97.5464 478.215 97.5464C476.918 97.5464 475.867 96.4953 475.867 95.1987C475.867 93.9022 476.918 92.8511 478.215 92.8511C479.511 92.8511 480.562 93.9022 480.562 95.1987Z" fill="#d6e2fb"/>
<path d="M504.949 95.2594C504.949 93.9293 506.027 92.8511 507.358 92.8511H585.228C586.558 92.8511 587.636 93.9293 587.636 95.2594C587.636 96.5895 586.558 97.6678 585.228 97.6678H507.358C506.027 97.6678 504.949 96.5895 504.949 95.2594Z" fill="#d6e2fb"/>
<path d="M595.664 95.2594C595.664 93.9293 596.742 92.8511 598.072 92.8511H609.311C610.642 92.8511 611.72 93.9293 611.72 95.2594C611.72 96.5895 610.642 97.6678 609.311 97.6678H598.072C596.742 97.6678 595.664 96.5895 595.664 95.2594Z" fill="#d6e2fb"/>
<path d="M480.562 111.254C480.562 112.551 479.511 113.602 478.215 113.602C476.918 113.602 475.867 112.551 475.867 111.254C475.867 109.958 476.918 108.907 478.215 108.907C479.511 108.907 480.562 109.958 480.562 111.254Z" fill="#d6e2fb"/>
<path d="M488.895 111.315C488.895 109.985 489.973 108.907 491.303 108.907H543.484C544.814 108.907 545.892 109.985 545.892 111.315C545.892 112.645 544.814 113.723 543.484 113.723H491.303C489.973 113.723 488.895 112.645 488.895 111.315Z" fill="#d6e2fb"/>
<path d="M553.92 111.315C553.92 109.985 554.998 108.907 556.328 108.907H633.396C634.726 108.907 635.804 109.985 635.804 111.315C635.804 112.645 634.726 113.723 633.396 113.723H556.328C554.998 113.723 553.92 112.645 553.92 111.315Z" fill="#d6e2fb"/>
<path d="M0 100.105C0 99.2184 0.71884 98.4995 1.60557 98.4995H353.226C354.113 98.4995 354.832 99.2184 354.832 100.105V116.008H0V100.105Z" fill="#c8d8fa"/>
<path d="M354.832 116.008H0V293.489C0 294.376 0.71884 295.095 1.60557 295.095H353.226C354.113 295.095 354.832 294.376 354.832 293.489V116.008Z" fill="#c8d8fa"/>
<path d="M354.832 116.008H0V293.489C0 294.376 0.71884 295.095 1.60557 295.095H353.226C354.113 295.095 354.832 294.376 354.832 293.489V116.008Z" fill="url(#paint2_linear_411_1552)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0 116.008H354.832V117.175H0V116.008Z" fill="#d6e2fb"/>
<path d="M17.0689 106.464C17.0689 108.349 15.5407 109.877 13.6555 109.877C11.7704 109.877 10.2422 108.349 10.2422 106.464C10.2422 104.579 11.7704 103.051 13.6555 103.051C15.5407 103.051 17.0689 104.579 17.0689 106.464Z" fill="#F9FAFB"/>
<path d="M29.5806 106.464C29.5806 108.349 28.0524 109.877 26.1673 109.877C24.2821 109.877 22.7539 108.349 22.7539 106.464C22.7539 104.579 24.2821 103.051 26.1673 103.051C28.0524 103.051 29.5806 104.579 29.5806 106.464Z" fill="#F9FAFB"/>
<path d="M42.0982 106.464C42.0982 108.349 40.57 109.877 38.6848 109.877C36.7997 109.877 35.2715 108.349 35.2715 106.464C35.2715 104.579 36.7997 103.051 38.6848 103.051C40.57 103.051 42.0982 104.579 42.0982 106.464Z" fill="#F9FAFB"/>
<path d="M17.0689 142.765C17.0689 144.65 15.5407 146.178 13.6555 146.178C11.7704 146.178 10.2422 144.65 10.2422 142.765C10.2422 140.88 11.7704 139.352 13.6555 139.352C15.5407 139.352 17.0689 140.88 17.0689 142.765Z" fill="#d6e2fb"/>
<path d="M29.1797 142.853C29.1797 140.919 30.7474 139.352 32.6813 139.352H108.55C110.484 139.352 112.052 140.919 112.052 142.853C112.052 144.787 110.484 146.355 108.55 146.355H32.6813C30.7474 146.355 29.1797 144.787 29.1797 142.853Z" fill="#d6e2fb"/>
<path d="M123.723 142.853C123.723 140.919 125.29 139.352 127.224 139.352H239.276C241.21 139.352 242.778 140.919 242.778 142.853C242.778 144.787 241.21 146.355 239.276 146.355H127.224C125.29 146.355 123.723 144.787 123.723 142.853Z" fill="#d6e2fb"/>
<path d="M17.0689 166.109C17.0689 167.994 15.5407 169.523 13.6555 169.523C11.7704 169.523 10.2422 167.994 10.2422 166.109C10.2422 164.224 11.7704 162.696 13.6555 162.696C15.5407 162.696 17.0689 164.224 17.0689 166.109Z" fill="#d6e2fb"/>
<path d="M52.5273 166.197C52.5273 164.264 54.0951 162.696 56.029 162.696H131.898C133.831 162.696 135.399 164.264 135.399 166.197C135.399 168.131 133.831 169.699 131.898 169.699H56.029C54.0951 169.699 52.5273 168.131 52.5273 166.197Z" fill="#d6e2fb"/>
<path d="M147.066 166.197C147.066 164.264 148.634 162.696 150.568 162.696H201.925C203.859 162.696 205.427 164.264 205.427 166.197C205.427 168.131 203.859 169.699 201.925 169.699H150.568C148.634 169.699 147.066 168.131 147.066 166.197Z" fill="#d6e2fb"/>
<path d="M17.0689 189.454C17.0689 191.339 15.5407 192.867 13.6555 192.867C11.7704 192.867 10.2422 191.339 10.2422 189.454C10.2422 187.568 11.7704 186.04 13.6555 186.04C15.5407 186.04 17.0689 187.568 17.0689 189.454Z" fill="#d6e2fb"/>
<path d="M29.1797 189.542C29.1797 187.608 30.7474 186.04 32.6813 186.04H108.55C110.484 186.04 112.052 187.608 112.052 189.542C112.052 191.476 110.484 193.044 108.55 193.044H32.6813C30.7474 193.044 29.1797 191.476 29.1797 189.542Z" fill="#d6e2fb"/>
<path d="M123.723 189.542C123.723 187.608 125.29 186.04 127.224 186.04H239.276C241.21 186.04 242.778 187.608 242.778 189.542C242.778 191.476 241.21 193.044 239.276 193.044H127.224C125.29 193.044 123.723 191.476 123.723 189.542Z" fill="#d6e2fb"/>
<path d="M17.0689 212.797C17.0689 214.683 15.5407 216.211 13.6555 216.211C11.7704 216.211 10.2422 214.683 10.2422 212.797C10.2422 210.912 11.7704 209.384 13.6555 209.384C15.5407 209.384 17.0689 210.912 17.0689 212.797Z" fill="#d6e2fb"/>
<path d="M52.5273 212.886C52.5273 210.952 54.0951 209.384 56.029 209.384H175.084C177.018 209.384 178.586 210.952 178.586 212.886C178.586 214.82 177.018 216.387 175.084 216.387H56.029C54.0951 216.387 52.5273 214.82 52.5273 212.886Z" fill="#d6e2fb"/>
<path d="M17.0689 236.142C17.0689 238.027 15.5407 239.555 13.6555 239.555C11.7704 239.555 10.2422 238.027 10.2422 236.142C10.2422 234.257 11.7704 232.729 13.6555 232.729C15.5407 232.729 17.0689 234.257 17.0689 236.142Z" fill="#d6e2fb"/>
<path d="M52.5273 236.23C52.5273 234.296 54.0951 232.729 56.029 232.729H169.248C171.182 232.729 172.75 234.296 172.75 236.23C172.75 238.164 171.182 239.732 169.248 239.732H56.029C54.0951 239.732 52.5273 238.164 52.5273 236.23Z" fill="#d6e2fb"/>
<path d="M184.418 236.23C184.418 234.296 185.986 232.729 187.92 232.729H204.261C206.194 232.729 207.762 234.296 207.762 236.23C207.762 238.164 206.194 239.732 204.261 239.732H187.92C185.986 239.732 184.418 238.164 184.418 236.23Z" fill="#d6e2fb"/>
<path d="M17.0689 259.486C17.0689 261.371 15.5407 262.899 13.6555 262.899C11.7704 262.899 10.2422 261.371 10.2422 259.486C10.2422 257.6 11.7704 256.072 13.6555 256.072C15.5407 256.072 17.0689 257.6 17.0689 259.486Z" fill="#d6e2fb"/>
<path d="M29.1797 259.574C29.1797 257.64 30.7474 256.072 32.6813 256.072H108.55C110.484 256.072 112.052 257.64 112.052 259.574C112.052 261.508 110.484 263.076 108.55 263.076H32.6813C30.7474 263.076 29.1797 261.508 29.1797 259.574Z" fill="#d6e2fb"/>
<path d="M123.723 259.574C123.723 257.64 125.29 256.072 127.224 256.072H239.276C241.21 256.072 242.778 257.64 242.778 259.574C242.778 261.508 241.21 263.076 239.276 263.076H127.224C125.29 263.076 123.723 261.508 123.723 259.574Z" fill="#d6e2fb"/>
<path d="M494.578 692.751H428V696.857C428 697.976 428.908 698.884 430.027 698.884H492.552C493.671 698.884 494.578 697.976 494.578 696.857V692.751Z" fill="#2563eb"/>
<path d="M494.578 692.751L488.446 648.95L463.041 638.876C465.523 650.264 461.727 673.917 449.463 677.421C432.943 682.141 428.146 689.101 428 692.751H494.578Z" fill="#d6e2fb"/>
<path d="M494.578 692.751L488.446 648.95L463.041 638.876C465.523 650.264 461.727 673.917 449.463 677.421C432.943 682.141 428.146 689.101 428 692.751H494.578Z" fill="url(#paint3_linear_411_1552)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M440.526 680.646C439.847 680.955 439.202 681.267 438.588 681.582C440.783 683.692 444.344 685.953 449.094 686.285C455.317 686.72 480.422 686.496 493.676 686.312L493.396 684.315C480.042 684.499 455.316 684.715 449.234 684.29C445.411 684.023 442.462 682.327 440.526 680.646Z" fill="#c8d8fa"/>
<path d="M391.576 692.751H324.999V696.857C324.999 697.976 325.906 698.884 327.025 698.884H389.55C390.669 698.884 391.576 697.976 391.576 696.857V692.751Z" fill="#2563eb"/>
<path d="M396.832 638L391.576 692.751H324.998C326.75 684.429 331.13 684.867 351.717 675.669C368.186 668.31 372.303 647.49 372.303 638H396.832Z" fill="#d6e2fb"/>
<path d="M396.832 638L391.576 692.751H324.998C326.75 684.429 331.13 684.867 351.717 675.669C368.186 668.31 372.303 647.49 372.303 638H396.832Z" fill="url(#paint4_linear_411_1552)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M340.872 680.315C342.793 682.098 345.874 684.005 349.949 684.29C355.777 684.697 378.727 684.516 392.383 684.338L392.191 686.341C378.532 686.518 355.703 686.697 349.809 686.285C344.729 685.93 341.01 683.369 338.866 681.146C339.225 680.997 339.593 680.845 339.971 680.688C340.265 680.566 340.565 680.442 340.872 680.315Z" fill="#c8d8fa"/>
<path d="M365 324.5L377 304.5L396.5 311.5C391.5 319.333 380 335.7 374 338.5C368 341.3 336.833 346.667 322 349H301.5C301.5 348.333 303 346.1 309 342.5C315 338.9 348.833 329 365 324.5Z" fill="#FDBA8C"/>
<path d="M365 324.5L377 304.5L396.5 311.5C391.5 319.333 380 335.7 374 338.5C368 341.3 336.833 346.667 322 349H301.5C301.5 348.333 303 346.1 309 342.5C315 338.9 348.833 329 365 324.5Z" fill="url(#paint5_linear_411_1552)"/>
<path d="M449 193C448.6 201.4 436.5 214.833 430.5 220.5L413 190.5C408 190.167 398 187.5 398 179.5C398 169.5 405 168.5 413 169.5C421 170.5 419 167 428 167C437 167 435 178.5 441 179.5C447 180.5 449.5 182.5 449 193Z" fill="#111928"/>
<path d="M421.5 237L419 207.5L427.5 200.5L440.5 233L421.5 237Z" fill="#FDBA8C"/>
<path d="M421.5 237L419 207.5L427.5 200.5L440.5 233L421.5 237Z" fill="url(#paint6_linear_411_1552)"/>
<path d="M421.001 216C411.264 217.168 408.006 197.892 407.533 187.337C407.509 186.8 407.719 186.264 408.109 185.893C419.283 175.238 427.054 195.5 430.001 195.5C433.001 195.5 435.501 191.5 437.001 194.5C438.501 197.5 433.501 214.5 421.001 216Z" fill="#FDBA8C"/>
<path d="M416.517 196.201C416.74 196.848 416.397 197.553 415.751 197.777C415.104 198.001 414.398 197.658 414.175 197.011C413.951 196.364 414.294 195.659 414.941 195.435C415.587 195.211 416.293 195.554 416.517 196.201Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M413.649 192.224C412.825 192.52 412.274 193.102 411.808 193.814C411.628 194.09 411.259 194.167 410.983 193.987C410.708 193.807 410.631 193.438 410.811 193.162C411.348 192.34 412.08 191.522 413.246 191.102C414.405 190.685 415.895 190.698 417.882 191.322C418.196 191.421 418.371 191.755 418.272 192.069C418.174 192.383 417.839 192.558 417.525 192.459C415.685 191.881 414.479 191.925 413.649 192.224Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M410.394 196.572C410.723 196.566 410.994 196.829 410.999 197.158L411.138 205.354C411.14 205.5 411.277 205.607 411.42 205.574L414.842 204.788C415.163 204.714 415.483 204.914 415.556 205.235C415.63 205.556 415.43 205.876 415.109 205.949L411.686 206.736C410.805 206.938 409.962 206.278 409.946 205.374L409.808 197.178C409.802 196.849 410.065 196.577 410.394 196.572Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M416.818 209.395C417.425 208.94 418 208.349 418.862 207.42C419.086 207.178 419.463 207.164 419.704 207.388C419.945 207.612 419.959 207.989 419.736 208.23C418.881 209.151 418.238 209.82 417.532 210.348C416.813 210.887 416.051 211.265 414.968 211.651C414.658 211.761 414.317 211.599 414.207 211.289C414.097 210.979 414.258 210.638 414.568 210.528C415.579 210.169 416.224 209.84 416.818 209.395Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M434.356 198.943C434.558 198.091 434.373 197.311 434.032 196.532C433.9 196.23 434.037 195.879 434.338 195.747C434.64 195.615 434.991 195.752 435.123 196.054C435.517 196.954 435.802 198.014 435.515 199.219C435.23 200.418 434.404 201.658 432.794 202.98C432.539 203.188 432.164 203.152 431.955 202.897C431.746 202.643 431.783 202.267 432.038 202.059C433.528 200.835 434.151 199.802 434.356 198.943Z" fill="#111928"/>
<path d="M450 662.5L430 487L406 662.5H356.5L403 349H467L499.5 662.5H450Z" fill="#2563eb"/>
<path d="M450 662.5L430 487L406 662.5H356.5L403 349H467L499.5 662.5H450Z" fill="url(#paint7_linear_411_1552)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M459.099 369.986C448.412 369.647 441.425 363.355 439.174 360.065L440.824 358.935C442.844 361.887 449.593 368 459.999 368H460.898L490.758 648.182C490.947 649.955 489.557 651.5 487.775 651.5H448.499V649.5H487.775C488.369 649.5 488.832 648.985 488.769 648.394L459.099 369.986Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M400.001 370C411.195 370 418.513 363.446 420.826 360.065L419.176 358.935C417.156 361.887 410.407 368 400.001 368V370Z" fill="#9ab7f6"/>
<path d="M396.5 319.5L372 305C394.5 244.5 416 231.5 428.5 229.5C441 227.5 469 231 475 252C479.8 268.8 474.333 286 471 292.5V350.5C471 351.605 470.105 352.5 469 352.5H400.854C399.808 352.5 398.939 351.694 398.86 350.651L396.5 319.5Z" fill="#F9FAFB"/>
<path d="M396.5 319.5L372 305C394.5 244.5 416 231.5 428.5 229.5C441 227.5 469 231 475 252C479.8 268.8 474.333 286 471 292.5V350.5C471 351.605 470.105 352.5 469 352.5H400.854C399.808 352.5 398.939 351.694 398.86 350.651L396.5 319.5Z" fill="url(#paint8_linear_411_1552)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M443.418 265.394L425.418 307.394L423.58 306.606L441.58 264.606L443.418 265.394Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M372 305L396.5 319.5L391.5 263L391.38 263.033C385.142 273.536 378.614 287.216 372 305Z" fill="url(#paint9_linear_411_1552)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M470.999 292.5C464.599 303.3 454.665 315 450.499 319.5L435.999 312.5L434.499 314.5L440.999 319.5L397.863 337.513L398.859 350.651C398.938 351.694 399.807 352.5 400.853 352.5H468.999C470.103 352.5 470.999 351.605 470.999 350.5V292.5Z" fill="url(#paint10_linear_411_1552)"/>
<path d="M358 340.5C346.8 341.7 342 346.667 341 349H368.5C371.833 349 385 347.3 411 340.5C437 333.7 446.833 323 448.5 318.5L428 308.5L422 317.5C405 324.333 369.2 339.3 358 340.5Z" fill="#FDBA8C"/>
<path d="M358 340.5C346.8 341.7 342 346.667 341 349H368.5C371.833 349 385 347.3 411 340.5C437 333.7 446.833 323 448.5 318.5L428 308.5L422 317.5C405 324.333 369.2 339.3 358 340.5Z" fill="url(#paint11_linear_411_1552)"/>
<path d="M388 357H334V699H388V357Z" fill="url(#paint12_linear_411_1552)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M387 699L387 359L389 359L389 699L387 699Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M387 699L387 359L389 359L389 699L387 699Z" fill="url(#paint13_linear_411_1552)"/>
<path d="M228 357H334V699H228V357Z" fill="url(#paint14_linear_411_1552)"/>
<path d="M223 354C223 352.895 223.895 352 225 352H391C392.105 352 393 352.895 393 354V357C393 358.105 392.105 359 391 359H225C223.895 359 223 358.105 223 357V354Z" fill="#d6e2fb"/>
<path d="M223 354C223 352.895 223.895 352 225 352H391C392.105 352 393 352.895 393 354V357C393 358.105 392.105 359 391 359H225C223.895 359 223 358.105 223 357V354Z" fill="url(#paint15_linear_411_1552)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M333 699L333 359L335 359L335 699L333 699Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M333 699L333 359L335 359L335 699L333 699Z" fill="url(#paint16_linear_411_1552)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M227 699L227 359L229 359L229 699L227 699Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M227 699L227 359L229 359L229 699L227 699Z" fill="url(#paint17_linear_411_1552)"/>
<path d="M330 354C330 352.895 330.895 352 332 352H391C392.105 352 393 352.895 393 354V357C393 358.105 392.105 359 391 359H332C330.895 359 330 358.105 330 357V354Z" fill="#d6e2fb"/>
<path d="M330 354C330 352.895 330.895 352 332 352H391C392.105 352 393 352.895 393 354V357C393 358.105 392.105 359 391 359H332C330.895 359 330 358.105 330 357V354Z" fill="url(#paint18_linear_411_1552)"/>
<path d="M243.727 302.549C243.362 301.272 244.321 300 245.65 300H314.49C315.383 300 316.167 300.592 316.413 301.451L329.998 349H256.998L243.727 302.549Z" fill="#2563eb"/>
<path d="M329.998 349H372.498C373.326 349 373.998 349.672 373.998 350.5C373.998 351.328 373.326 352 372.498 352H329.998V349Z" fill="#111928"/>
<path d="M329.998 349H256.998V350C256.998 351.105 257.893 352 258.998 352H329.998V349Z" fill="#111928"/>
<path d="M289.806 324.889C290.253 326.485 289.271 327.778 287.615 327.778C285.958 327.778 284.253 326.485 283.806 324.889C283.36 323.293 284.341 322 285.998 322C287.655 322 289.36 323.293 289.806 324.889Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M162.698 479.891C162.698 482.233 162.272 484.476 161.492 486.546C160.844 488.268 161.288 490.285 162.694 491.472C169.056 496.844 173.097 504.88 173.097 513.859C173.097 517.037 172.591 520.096 171.655 522.961C171.169 524.448 171.578 526.105 172.741 527.152C181.472 535.019 186.961 546.415 186.961 559.093C186.961 582.83 167.718 602.074 143.981 602.074C120.243 602.074 101 582.83 101 559.093C101 546.533 106.387 535.232 114.977 527.373C116.131 526.317 116.528 524.657 116.03 523.174C115.049 520.248 114.518 517.116 114.518 513.859C114.518 504.88 118.559 496.844 124.921 491.472C126.327 490.285 126.771 488.268 126.122 486.546C125.343 484.476 124.917 482.233 124.917 479.891C124.917 469.458 133.374 461 143.807 461C154.24 461 162.698 469.458 162.698 479.891Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M143.635 478.717C144.187 478.717 144.635 479.165 144.635 479.717L144.635 645.747C144.635 646.3 144.187 646.747 143.635 646.747C143.082 646.747 142.635 646.3 142.635 645.747L142.635 479.717C142.635 479.165 143.082 478.717 143.635 478.717Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M163.279 553.774C163.728 554.095 163.832 554.719 163.511 555.169L144.447 581.858C144.126 582.308 143.502 582.412 143.052 582.091C142.603 581.77 142.499 581.145 142.82 580.696L161.884 554.006C162.205 553.557 162.829 553.453 163.279 553.774Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M123.989 512.526C123.54 512.847 123.435 513.471 123.757 513.921L142.821 540.61C143.142 541.06 143.766 541.164 144.215 540.843C144.665 540.522 144.769 539.897 144.448 539.448L125.384 512.758C125.063 512.309 124.438 512.205 123.989 512.526Z" fill="#9ab7f6"/>
<path d="M116.689 621.839C116.64 620.701 117.549 619.751 118.688 619.751H168.927C170.066 619.751 170.975 620.701 170.925 621.839L167.635 696.868C167.588 697.937 166.707 698.78 165.637 698.78H121.978C120.908 698.78 120.027 697.937 119.98 696.868L116.689 621.839Z" fill="#d6e2fb"/>
<path d="M116.689 621.839C116.64 620.701 117.549 619.751 118.688 619.751H168.927C170.066 619.751 170.975 620.701 170.925 621.839L167.635 696.868C167.588 697.937 166.707 698.78 165.637 698.78H121.978C120.908 698.78 120.027 697.937 119.98 696.868L116.689 621.839Z" fill="url(#paint19_linear_411_1552)"/>
<defs>
<linearGradient id="paint0_linear_411_1552" x1="626.977" y1="336.817" x2="626.977" y2="686.876" gradientUnits="userSpaceOnUse">
<stop stop-color="#2563eb" stop-opacity="0"/>
<stop offset="1" stop-color="#2563eb"/>
</linearGradient>
<linearGradient id="paint1_linear_411_1552" x1="590.848" y1="59.8556" x2="590.848" y2="409.914" gradientUnits="userSpaceOnUse">
<stop stop-color="#2563eb" stop-opacity="0"/>
<stop offset="1" stop-color="#2563eb"/>
</linearGradient>
<linearGradient id="paint2_linear_411_1552" x1="177.416" y1="184.755" x2="177.416" y2="693.722" gradientUnits="userSpaceOnUse">
<stop stop-color="#2563eb" stop-opacity="0"/>
<stop offset="1" stop-color="#2563eb"/>
</linearGradient>
<linearGradient id="paint3_linear_411_1552" x1="445.89" y1="629.678" x2="445.89" y2="679.173" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint4_linear_411_1552" x1="344.3" y1="628.652" x2="344.3" y2="678.952" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint5_linear_411_1552" x1="404" y1="386.5" x2="355.5" y2="317" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint6_linear_411_1552" x1="432.648" y1="193.452" x2="432.648" y2="228.468" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint7_linear_411_1552" x1="460.833" y1="562.5" x2="635.814" y2="367.586" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928" stop-opacity="0"/>
<stop offset="1" stop-color="#111928"/>
</linearGradient>
<linearGradient id="paint8_linear_411_1552" x1="506.422" y1="352.5" x2="432.024" y2="230.429" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint9_linear_411_1552" x1="402" y1="317" x2="387.947" y2="296.217" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint10_linear_411_1552" x1="443" y1="317" x2="430.143" y2="361.132" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint11_linear_411_1552" x1="495.5" y1="362.5" x2="409.238" y2="339.65" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint12_linear_411_1552" x1="361" y1="342.71" x2="361" y2="631.362" gradientUnits="userSpaceOnUse">
<stop stop-color="#F9FAFB"/>
<stop offset="1" stop-color="#F9FAFB" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint13_linear_411_1552" x1="387.804" y1="359" x2="384.158" y2="359.105" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint14_linear_411_1552" x1="281" y1="342.71" x2="281" y2="631.362" gradientUnits="userSpaceOnUse">
<stop stop-color="#F9FAFB"/>
<stop offset="1" stop-color="#F9FAFB" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint15_linear_411_1552" x1="179.5" y1="344" x2="386" y2="395.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint16_linear_411_1552" x1="333.804" y1="359" x2="330.158" y2="359.105" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint17_linear_411_1552" x1="227.804" y1="359" x2="224.158" y2="359.105" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint18_linear_411_1552" x1="313.879" y1="344" x2="394.477" y2="351.449" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint19_linear_411_1552" x1="236.32" y1="673.369" x2="78.7637" y2="673.369" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
</defs>
</svg>
SVG,
        'step3' => <<<'SVG'
<svg class="w-auto max-w-[16rem] h-40 text-gray-800 dark:text-white" aria-hidden="true" width="561" height="651" viewBox="0 0 561 651" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M274 344H295L367.675 644.52C368.35 647.312 366.235 650 363.362 650C361.374 650 359.628 648.677 359.09 646.763L274 344Z" fill="#d6e2fb"/>
<path d="M274 344H295L367.675 644.52C368.35 647.312 366.235 650 363.362 650C361.374 650 359.628 648.677 359.09 646.763L274 344Z" fill="url(#paint0_linear_182_7950)"/>
<path d="M170 344H149L76.3252 644.52C75.65 647.312 77.7655 650 80.638 650C82.6262 650 84.3717 648.677 84.9096 646.763L170 344Z" fill="#d6e2fb"/>
<path d="M170 344H149L76.3252 644.52C75.65 647.312 77.7655 650 80.638 650C82.6262 650 84.3717 648.677 84.9096 646.763L170 344Z" fill="url(#paint1_linear_182_7950)"/>
<path d="M120.757 80.3517C121.493 77.7759 123.847 76 126.526 76H489.046C493.032 76 495.91 79.8154 494.815 83.6483L418.244 351.648C417.508 354.224 415.154 356 412.475 356H49.9548C45.9686 356 43.0906 352.185 44.1857 348.352L120.757 80.3517Z" fill="#d6e2fb"/>
<path d="M120.757 80.3517C121.493 77.7759 123.847 76 126.526 76H489.046C493.032 76 495.91 79.8154 494.815 83.6483L418.244 351.648C417.508 354.224 415.154 356 412.475 356H49.9548C45.9686 356 43.0906 352.185 44.1857 348.352L120.757 80.3517Z" fill="url(#paint2_linear_182_7950)"/>
<path d="M110.757 80.3517C111.493 77.7759 113.847 76 116.526 76H479.046C483.032 76 485.91 79.8154 484.815 83.6483L408.244 351.648C407.508 354.224 405.154 356 402.475 356H39.9548C35.9686 356 33.0906 352.185 34.1857 348.352L110.757 80.3517Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M432.873 162.366C425.323 165.398 416.013 170.609 403.991 177.372C397.813 180.847 392.456 181.623 387.494 180.767C382.577 179.919 378.139 177.481 373.778 174.72C372.614 173.983 371.455 173.224 370.291 172.461C362.636 167.447 354.761 162.289 344.02 162.5C341.087 162.557 337.744 163.809 334.046 166.083C330.359 168.351 326.404 171.583 322.264 175.486C313.984 183.292 305.083 193.67 296.263 204.084C295.584 204.887 294.904 205.69 294.226 206.492C286.122 216.071 278.136 225.51 270.878 232.733C266.944 236.647 263.192 239.942 259.723 242.264C256.275 244.572 252.986 246 250 246C244.688 246 240.425 245.338 236.54 244.734L236.214 244.684C232.232 244.066 228.662 243.538 224.63 243.811C216.601 244.354 206.539 248.095 188.083 261.313C169.553 274.585 155.207 282.777 141.942 287.199C128.647 291.63 116.492 292.257 102.376 290.492C80.4344 287.75 66.8499 300.128 62.8483 306.53L61.1523 305.47C65.4841 298.539 79.7662 285.651 102.624 288.508C116.509 290.243 128.354 289.62 141.309 285.302C154.293 280.973 168.448 272.916 186.918 259.687C205.462 246.406 215.899 242.397 224.495 241.815C228.776 241.525 232.55 242.091 236.521 242.707L236.819 242.754C240.712 243.358 244.844 244 250 244C252.39 244 255.273 242.835 258.61 240.602C261.926 238.383 265.573 235.19 269.467 231.315C276.661 224.157 284.591 214.783 292.713 205.183C293.387 204.387 294.061 203.589 294.737 202.791C303.543 192.393 312.517 181.927 320.892 174.03C325.082 170.081 329.149 166.747 332.998 164.38C336.835 162.02 340.538 160.568 343.981 160.5C355.386 160.277 363.812 165.81 371.442 170.821C372.593 171.576 373.726 172.32 374.848 173.03C379.174 175.769 383.33 178.019 387.834 178.796C392.295 179.565 397.188 178.903 403.01 175.629C414.988 168.891 424.428 163.602 432.128 160.51C439.818 157.421 446.003 156.424 451.372 158.572L450.629 160.429C445.998 158.576 440.433 159.329 432.873 162.366Z" fill="#9ab7f6"/>
<path d="M402.258 236H423.258L401 310.757H380L402.258 236Z" fill="white"/>
<path d="M363.921 264H384.921L371 310.757H350L363.921 264Z" fill="white"/>
<path d="M328.562 282H349.562L341.001 310.757H320.001L328.562 282Z" fill="white"/>
<path d="M454.778 159.223C454.216 161.003 452.247 162.446 450.38 162.446C448.513 162.446 447.456 161.003 448.018 159.223C448.58 157.443 450.549 156 452.416 156C454.283 156 455.34 157.443 454.778 159.223Z" fill="white"/>
<path d="M340.506 163.768C339.674 166.401 336.761 168.536 334 168.536C331.238 168.536 329.674 166.401 330.506 163.768C331.337 161.135 334.25 159 337.012 159C339.773 159 341.337 161.135 340.506 163.768Z" fill="#2563eb"/>
<path d="M64.8854 305.768C64.3232 307.548 62.3541 308.991 60.4874 308.991C58.6207 308.991 57.5632 307.548 58.1255 305.768C58.6878 303.988 60.6568 302.545 62.5235 302.545C64.3902 302.545 65.4477 303.988 64.8854 305.768Z" fill="white"/>
<path d="M206.5 603.03L210 636.03L250.5 648.03L278 645.03C278.833 643.864 280.2 641.03 279 639.03C277.5 636.53 269 634.03 255 626.53C243.8 620.53 238.667 608.364 237.5 603.03H206.5Z" fill="#d6e2fb"/>
<path d="M206.5 603.03L210 636.03L250.5 648.03L278 645.03C278.833 643.864 280.2 641.03 279 639.03C277.5 636.53 269 634.03 255 626.53C243.8 620.53 238.667 608.364 237.5 603.03H206.5Z" fill="url(#paint3_linear_182_7950)"/>
<path d="M210 636.03L210.867 648.173C210.942 649.219 211.813 650.03 212.862 650.03H268.79C274.207 650.03 281.412 648.78 280.637 643.419C280.392 641.721 279.674 640.109 279 639.03C278 644.53 274 645.03 260 645.03C246 645.03 237.5 639.53 227 635.53C218.6 632.33 212.167 634.53 210 636.03Z" fill="#374151"/>
<path d="M111 641.03L114.5 604.03L141 603.03L146.5 645.03L111 641.03Z" fill="#d6e2fb"/>
<path d="M111 641.03L114.5 604.03L141 603.03L146.5 645.03L111 641.03Z" fill="url(#paint4_linear_182_7950)"/>
<path d="M145.5 650.03H111.222C110.034 650.03 109.109 649.002 109.233 647.821L110 640.53C111 638.364 115.8 634.03 127 634.03C138.2 634.03 145.334 641.697 147.5 645.53V648.03C147.5 649.135 146.605 650.03 145.5 650.03Z" fill="#374151"/>
<path d="M321.136 125.198C320.785 124.151 321.349 123.017 322.396 122.666C323.444 122.315 324.577 122.879 324.928 123.927L334.781 153.319L334.156 157.747L330.989 154.591L321.136 125.198Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M241 205.03C261.667 188.364 304.1 153.83 308.5 149.03C310.079 147.308 311.492 145.339 312.871 143.419C316.297 138.647 319.509 134.174 324.5 134.53C331.428 135.025 333 135.53 333 142.53C335.167 144.03 339 148.13 337 152.53C336.667 153.03 335.6 153.73 334 152.53C332 151.03 325.5 150.53 322 151.03C319.2 151.43 281.834 192.864 263.5 213.53C256 220.864 238.8 233.63 230 226.03C221.2 218.43 233.667 208.864 241 205.03ZM331 141.03C331 140.53 330.5 139.53 329.5 139.03C328.369 138.465 327 138.53 326 139.03L331 141.03Z" fill="#FDBA8C"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M241 205.03C261.667 188.364 304.1 153.83 308.5 149.03C310.079 147.308 311.492 145.339 312.871 143.419C316.297 138.647 319.509 134.174 324.5 134.53C331.428 135.025 333 135.53 333 142.53C335.167 144.03 339 148.13 337 152.53C336.667 153.03 335.6 153.73 334 152.53C332 151.03 325.5 150.53 322 151.03C319.2 151.43 281.834 192.864 263.5 213.53C256 220.864 238.8 233.63 230 226.03C221.2 218.43 233.667 208.864 241 205.03ZM331 141.03C331 140.53 330.5 139.53 329.5 139.03C328.369 138.465 327 138.53 326 139.03L331 141.03Z" fill="url(#paint5_linear_182_7950)"/>
<path d="M201.255 604.312L171.5 395.53L151.176 604.224C151.076 605.249 150.215 606.03 149.186 606.03H107.216C106.031 606.03 105.105 605.005 105.226 603.826L138 284.53H205L248.193 603.762C248.356 604.962 247.423 606.03 246.211 606.03H203.235C202.24 606.03 201.396 605.298 201.255 604.312Z" fill="#2563eb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M152.835 592.03H106.5V590.03H152.835V592.03Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M246.835 592.03H199V590.03H246.835V592.03Z" fill="#d6e2fb"/>
<path d="M125 606.03L161 321.53L171.5 395.459L151 606.03H125Z" fill="url(#paint6_linear_182_7950)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M205.426 301.409C202.226 309.231 195.316 312.581 190.846 313.646C190.143 313.814 189.736 314.444 189.823 314.993L233.488 590.874L231.512 591.187L187.848 315.306C187.572 313.564 188.828 312.071 190.382 311.701C194.478 310.724 200.703 307.671 203.574 300.652L205.426 301.409Z" fill="#d6e2fb"/>
<path d="M153 148.53C159 156.13 162.833 162.03 164 164.03C169.999 162.83 176.5 142.197 179 132.03C181 130.864 185.1 128.03 185.5 126.03C186 123.53 182 119.03 169.5 115.53C159.5 112.73 154.333 121.697 153 126.53C145 128.03 145.5 139.03 153 148.53Z" fill="#111928"/>
<path d="M162 182.53L164 149.03L177 157.03L177.5 182.53H162Z" fill="#FDBA8C"/>
<path d="M162 182.53L164 149.03L177 157.03L177.5 182.53H162Z" fill="url(#paint7_linear_182_7950)"/>
<path d="M184 160.53C191.6 156.53 185.167 137.863 181 129.03C177 126.53 171.5 143.53 167.5 141.03C164.785 139.333 158 134.53 160 142.53C162 150.53 174.5 165.53 184 160.53Z" fill="#FDBA8C"/>
<path d="M178.134 140.302C178.039 140.868 178.42 141.404 178.986 141.5C179.553 141.596 180.089 141.215 180.185 140.648C180.281 140.082 179.899 139.545 179.333 139.45C178.767 139.354 178.23 139.735 178.134 140.302Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M178.907 136.46C179.641 136.415 180.259 136.681 180.854 137.075C181.084 137.227 181.395 137.164 181.547 136.934C181.7 136.704 181.637 136.394 181.406 136.241C180.719 135.786 179.883 135.398 178.846 135.462C177.814 135.526 176.669 136.03 175.345 137.172C175.136 137.352 175.113 137.668 175.293 137.877C175.473 138.086 175.789 138.109 175.998 137.929C177.224 136.872 178.168 136.506 178.907 136.46Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M183.275 139.678C183.017 139.777 182.889 140.066 182.988 140.324L185.461 146.743C185.505 146.858 185.433 146.984 185.311 147.003L182.401 147.469C182.129 147.513 181.943 147.77 181.987 148.042C182.03 148.315 182.287 148.501 182.56 148.457L185.47 147.991C186.219 147.871 186.667 147.091 186.394 146.383L183.921 139.964C183.822 139.707 183.532 139.578 183.275 139.678Z" fill="#111928"/>
<path d="M185.826 155.075C180.832 155.997 178.249 153.884 177.066 150.629L185.826 150.629L185.826 155.075Z" fill="white"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M163.65 144.163C163.365 143.486 163.411 142.815 163.585 142.123C163.653 141.855 163.491 141.583 163.223 141.515C162.955 141.448 162.684 141.61 162.616 141.878C162.414 142.677 162.325 143.594 162.729 144.552C163.131 145.505 163.986 146.417 165.501 147.289C165.741 147.426 166.046 147.344 166.184 147.104C166.322 146.865 166.239 146.559 166 146.422C164.597 145.615 163.938 144.846 163.65 144.163Z" fill="#111928"/>
<path d="M230.043 233.661L209.5 224.53V286.03C209.5 287.135 208.605 288.03 207.5 288.03H138.34C137.3 288.03 136.435 287.257 136.355 286.22C133.686 251.633 134.419 184.64 158 179.53C181.366 174.468 222.742 192.059 241.984 202.22C242.862 202.684 243.248 203.721 242.907 204.654L232.734 232.519C232.337 233.607 231.102 234.131 230.043 233.661Z" fill="#F9FAFB"/>
<path d="M183.5 288.03V212.53L209.5 224.324V288.03H183.5Z" fill="url(#paint8_linear_182_7950)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M31.1371 461.875L73.5965 461.875C74.4461 461.875 75.1348 461.186 75.1348 460.336L75.1348 448.306C75.1348 447.456 74.4461 446.768 73.5965 446.768L31.1371 446.768C30.2875 446.768 29.5988 447.456 29.5988 448.306L29.5988 460.336C29.5988 461.186 30.2875 461.875 31.1371 461.875ZM73.5965 463.875C75.5506 463.875 77.1348 462.29 77.1348 460.336L77.1348 448.306C77.1348 446.352 75.5506 444.768 73.5965 444.768L31.1371 444.768C29.183 444.768 27.5988 446.352 27.5988 448.306L27.5988 460.336C27.5988 462.29 29.1829 463.875 31.1371 463.875L73.5965 463.875Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M58.7357 437.106L101.195 437.106C102.045 437.106 102.733 436.418 102.733 435.568L102.733 423.538C102.733 422.688 102.045 422 101.195 422L58.7357 422C57.8861 422 57.1974 422.688 57.1974 423.538L57.1974 435.568C57.1974 436.418 57.8862 437.106 58.7357 437.106ZM101.195 439.106C103.149 439.106 104.733 437.522 104.733 435.568L104.733 423.538C104.733 421.584 103.149 420 101.195 420L58.7357 420C56.7816 420 55.1974 421.584 55.1974 423.538L55.1974 435.568C55.1974 437.522 56.7816 439.106 58.7357 439.106L101.195 439.106Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M3.53845 437.106L45.9979 437.106C46.8474 437.106 47.5361 436.418 47.5361 435.568L47.5361 423.538C47.5361 422.688 46.8474 422 45.9979 422L3.53846 422C2.68888 422 2.00017 422.688 2.00017 423.538L2.00017 435.568C2.00017 436.418 2.68888 437.106 3.53845 437.106ZM45.9979 439.106C47.952 439.106 49.5361 437.522 49.5361 435.568L49.5361 423.538C49.5361 421.584 47.952 420 45.9979 420L3.53846 420C1.58432 420 0.000173193 421.584 0.000173022 423.538L0.000171971 435.568C0.0001718 437.522 1.58431 439.106 3.53845 439.106L45.9979 439.106Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M529.596 2L487.137 2C486.287 2 485.599 2.68871 485.599 3.53828V15.5684C485.599 16.418 486.287 17.1067 487.137 17.1067L529.596 17.1067C530.446 17.1067 531.135 16.418 531.135 15.5684V3.53828C531.135 2.68871 530.446 2 529.596 2ZM487.137 0C485.183 0 483.599 1.58414 483.599 3.53828V15.5684C483.599 17.5226 485.183 19.1067 487.137 19.1067L529.596 19.1067C531.55 19.1067 533.135 17.5226 533.135 15.5684V3.53828C533.135 1.58414 531.55 0 529.596 0L487.137 0Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M501.998 26.7681L459.538 26.7681C458.689 26.7681 458 27.4568 458 28.3063V40.3365C458 41.1861 458.689 41.8748 459.538 41.8748L501.998 41.8748C502.847 41.8748 503.536 41.1861 503.536 40.3365V28.3063C503.536 27.4568 502.847 26.7681 501.998 26.7681ZM459.538 24.7681C457.584 24.7681 456 26.3522 456 28.3063V40.3365C456 42.2907 457.584 43.8748 459.538 43.8748L501.998 43.8748C503.952 43.8748 505.536 42.2907 505.536 40.3365V28.3063C505.536 26.3522 503.952 24.7681 501.998 24.7681L459.538 24.7681Z" fill="#d6e2fb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M557.195 26.7681L514.736 26.7681C513.886 26.7681 513.197 27.4568 513.197 28.3063V40.3365C513.197 41.1861 513.886 41.8748 514.736 41.8748L557.195 41.8748C558.045 41.8748 558.733 41.1861 558.733 40.3365V28.3063C558.733 27.4568 558.045 26.7681 557.195 26.7681ZM514.736 24.7681C512.781 24.7681 511.197 26.3522 511.197 28.3063V40.3365C511.197 42.2907 512.781 43.8748 514.736 43.8748L557.195 43.8748C559.149 43.8748 560.733 42.2907 560.733 40.3365V28.3063C560.733 26.3522 559.149 24.7681 557.195 24.7681L514.736 24.7681Z" fill="#d6e2fb"/>
<path d="M503 473L499.5 499H509C517.833 482.667 534.4 449.6 530 448C525.6 446.4 510.167 464 503 473Z" fill="#d6e2fb"/>
<path d="M503 473L499.5 499H509C517.833 482.667 534.4 449.6 530 448C525.6 446.4 510.167 464 503 473Z" fill="url(#paint9_linear_182_7950)"/>
<path d="M457 450C455.4 457.2 466.666 487 472.5 501H484.5L483 484.5C475 470 458.6 442.8 457 450Z" fill="#d6e2fb"/>
<path d="M457 450C455.4 457.2 466.666 487 472.5 501H484.5L483 484.5C475 470 458.6 442.8 457 450Z" fill="url(#paint10_linear_182_7950)"/>
<path d="M477 382C470.6 387.6 478 464.666 482.5 502.5H494L496 485C492.333 448.333 483.4 376.4 477 382Z" fill="#d6e2fb"/>
<path d="M477 382C470.6 387.6 478 464.666 482.5 502.5H494L496 485C492.333 448.333 483.4 376.4 477 382Z" fill="url(#paint11_linear_182_7950)"/>
<path d="M516 332.5C504 348.1 494 452.667 490.5 503H500.5C504.167 489 512.4 456.9 516 440.5C520.5 420 531 313 516 332.5Z" fill="#d6e2fb"/>
<path d="M529.936 499.062C529.971 497.934 529.066 497 527.937 497H457.063C455.934 497 455.029 497.934 455.064 499.062L459.716 648.062C459.75 649.142 460.635 650 461.716 650H523.284C524.365 650 525.25 649.142 525.283 648.062L529.936 499.062Z" fill="#d6e2fb"/>
<path d="M529.936 499.062C529.971 497.934 529.066 497 527.937 497H457.063C455.934 497 455.029 497.934 455.064 499.062L459.716 648.062C459.75 649.142 460.635 650 461.716 650H523.284C524.365 650 525.25 649.142 525.283 648.062L529.936 499.062Z" fill="url(#paint12_linear_182_7950)"/>
<defs>
<linearGradient id="paint0_linear_182_7950" x1="56.5" y1="7.49997" x2="317.08" y2="-7.26075" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint1_linear_182_7950" x1="387.5" y1="7.49997" x2="126.92" y2="-7.26075" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint2_linear_182_7950" x1="558" y1="443.5" x2="380" y2="-7.49996" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint3_linear_182_7950" x1="269" y1="600.53" x2="268.87" y2="628.394" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint4_linear_182_7950" x1="150" y1="600.03" x2="145.345" y2="631.993" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint5_linear_182_7950" x1="70.5" y1="378.53" x2="102.458" y2="142.795" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint6_linear_182_7950" x1="308" y1="210.53" x2="274.957" y2="513.146" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint7_linear_182_7950" x1="189.5" y1="151.03" x2="163.5" y2="158.53" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint8_linear_182_7950" x1="246" y1="213.03" x2="187.938" y2="263.147" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint9_linear_182_7950" x1="450.5" y1="530.5" x2="521.5" y2="465" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint10_linear_182_7950" x1="523" y1="520.5" x2="454.459" y2="484.096" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint11_linear_182_7950" x1="555" y1="533" x2="472.64" y2="463.415" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint12_linear_182_7950" x1="365" y1="600.804" x2="536.5" y2="600.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6"/>
<stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
</linearGradient>
</defs>
</svg>
SVG,
    ];

    echo $illustrations[$type] ?? $illustrations['welcome'];
}

function onboarding_render_progress_steps(int $currentStep, array $status): void
{
    $stepLabels = [
        1 => 'Passwort',
        2 => 'Profil',
        3 => 'Kontakt',
        4 => 'Avatar',
    ];
    $totalSteps = onboarding_step_count();
    $connectorClasses = "after:content-[''] after:w-4 sm:after:w-8 after:h-1 after:border-b after:border-gray-200 after:border-1 after:inline-block after:mx-1 sm:after:mx-3 xl:after:mx-5 dark:after:border-gray-700";
    $innerBaseClasses = 'flex flex-col items-center text-center min-w-[2.5rem] sm:min-w-[3.25rem]';
    $checkIcon = '<svg class="w-full h-full" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>';

    ?>
<div class="onboarding-progress-wrap">
<ol class="flex items-center justify-center w-full text-sm font-medium text-center text-gray-500 dark:text-gray-400 sm:text-base" aria-label="Onboarding-Fortschritt">
    <?php foreach ($stepLabels as $stepNum => $label):
        $isDone = !empty($status['step' . $stepNum . '_completed'])
            || ($stepNum === 3 && $currentStep === 0 && !empty($_SESSION['onboarding_contact_step_seen']))
            || ($stepNum === 4 && $currentStep === 0 && !empty($_SESSION['onboarding_avatar_step_seen']));
        $isCurrent = ($currentStep === $stepNum);
        $accessible = onboarding_step_is_accessible($stepNum, $status);
        $isLast = ($stepNum === $totalSteps);

        $liClasses = ['flex', 'items-center'];
        if (!$isLast) {
            $liClasses[] = $connectorClasses;
        }
        if ($isDone || $isCurrent) {
            $liClasses[] = 'text-primary-600 dark:text-primary-500';
        }
        ?>
    <li class="<?php echo implode(' ', $liClasses); ?>">
        <?php if ($accessible && !$isCurrent): ?>
        <a href="<?php echo htmlspecialchars(onboarding_step_url($stepNum)); ?>" class="<?php echo $innerBaseClasses; ?> no-underline hover:opacity-90">
        <?php else: ?>
        <div class="<?php echo $innerBaseClasses; ?>"<?php echo $isCurrent ? ' aria-current="step"' : ''; ?>>
        <?php endif; ?>
            <span class="onboarding-progress-icon">
            <?php if ($isDone): ?>
            <?php echo $checkIcon; ?>
            <?php else: ?>
            <?php echo $stepNum; ?>
            <?php endif; ?>
            </span>
            <span class="onboarding-progress-label"><?php echo htmlspecialchars($label); ?></span>
        <?php if ($accessible && !$isCurrent): ?>
        </a>
        <?php else: ?>
        </div>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ol>
</div>
    <?php
}

/**
 * @return list<array{title: string, text: string}>
 */
function onboarding_tips_data(): array
{
    return [
        [
            'title' => 'Alles an einem Ort',
            'text' => 'Serohub bündelt Tickets, Aufgaben und Kommunikation – du behältst jederzeit den Überblick über deine Anliegen.',
        ],
        [
            'title' => 'Tickets erstellen',
            'text' => 'Neues Problem oder Wunsch? Lege ein Ticket an – unser Team sieht sofort, worum es geht und wer zuständig ist.',
        ],
        [
            'title' => 'Dein Dashboard',
            'text' => 'Nach dem Onboarding findest du auf dem Dashboard offene Tickets, Termine und wichtige Hinweise auf einen Blick.',
        ],
        [
            'title' => 'Prioritäten setzen',
            'text' => 'Bei dringenden Themen wähle eine höhere Priorität – so wird dein Ticket schneller bearbeitet.',
        ],
        [
            'title' => 'Wissensdatenbank',
            'text' => 'Viele Antworten findest du in der Wissensdatenbank – oft schneller als ein neues Ticket.',
        ],
        [
            'title' => 'Benachrichtigungen',
            'text' => 'Unter Einstellungen legst du fest, wann wir dich per E-Mail oder im Portal über Updates informieren.',
        ],
        [
            'title' => 'Sicheres Passwort',
            'text' => 'Nutze ein einzigartiges Passwort mit Buchstaben, Zahlen und Sonderzeichen – so schützt du dein Konto zuverlässig.',
        ],
        [
            'title' => 'Profil pflegen',
            'text' => 'Ein vollständiges Profil hilft Kollegen und dem Support, dich schnell und richtig anzusprechen.',
        ],
        [
            'title' => 'Erreichbarkeit',
            'text' => 'Telefon, Mobil oder bevorzugter Kanal – je genauer deine Angaben, desto schneller erreichen wir dich.',
        ],
        [
            'title' => 'Profilbild',
            'text' => 'Mit Avatar erkennen dich Teammitglieder in Kommentaren und Ticket-Verläufen auf einen Blick.',
        ],
        [
            'title' => 'Auch unterwegs',
            'text' => 'Serohub läuft im Browser auf dem Smartphone – melde dich einfach an und behalte Tickets im Blick.',
        ],
        [
            'title' => 'Noch sicherer anmelden',
            'text' => 'Später kannst du in den Einstellungen einen Passkey oder Zwei-Faktor-Authentifizierung aktivieren.',
        ],
    ];
}

function onboarding_render_tips_carousel(): void
{
    $tips = onboarding_tips_data();
    $total = count($tips);
    if ($total === 0) {
        return;
    }

    $badgeIcon = '<svg aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>';
    ?>
<aside class="onboarding-tips" data-onboarding-tips aria-label="Hilfreiche Hinweise zu Serohub">
    <div class="onboarding-tips__card">
        <div class="onboarding-tips__viewport" data-onboarding-tips-viewport>
            <?php foreach ($tips as $index => $tip): ?>
            <article class="onboarding-tips__slide<?php echo $index === 0 ? ' is-active' : ''; ?>"
                     data-tip-index="<?php echo (int) $index; ?>"
                     aria-hidden="<?php echo $index === 0 ? 'false' : 'true'; ?>">
                <div class="onboarding-tips__main">
                    <div class="onboarding-tips__badge">
                        <?php echo $badgeIcon; ?>
                        TIPP
                    </div>
                    <p class="onboarding-tips__line">
                        <strong class="onboarding-tips__title"><?php echo htmlspecialchars($tip['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="onboarding-tips__text"><?php echo htmlspecialchars($tip['text'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </p>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="onboarding-tips__footer">
            <div class="onboarding-tips__dots" role="tablist" aria-label="Tipp auswählen">
                <?php foreach ($tips as $index => $tip): ?>
                <button type="button"
                        class="onboarding-tips__dot<?php echo $index === 0 ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                        aria-label="Tipp <?php echo (int) $index + 1; ?>: <?php echo htmlspecialchars($tip['title'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-tip-dot="<?php echo (int) $index; ?>"></button>
                <?php endforeach; ?>
            </div>
            <span class="onboarding-tips__counter" data-onboarding-tips-counter>1 / <?php echo (int) $total; ?></span>
        </div>
    </div>
</aside>
    <?php
}

function onboarding_shell_open(array $config): void
{
    $illustration = $config['illustration'] ?? 'welcome';
    $currentStep = (int)($config['current_step'] ?? 0);
    $status = $config['status'] ?? [
        'step1_completed' => false,
        'step2_completed' => false,
        'step3_completed' => false,
        'step4_completed' => false,
    ];
    $shellModifier = trim((string)($config['shell_modifier'] ?? ''));
    $stepClass = trim((string)($config['step_class'] ?? ''));
    $shellClasses = 'onboarding-shell' . ($shellModifier !== '' ? ' ' . htmlspecialchars($shellModifier, ENT_QUOTES, 'UTF-8') : '');
    $stepBodyClasses = 'onboarding-step-body' . ($stepClass !== '' ? ' ' . htmlspecialchars($stepClass, ENT_QUOTES, 'UTF-8') : '');
    ?>
<main class="flex-1 w-full min-h-0 overflow-hidden">
  <div class="<?php echo $shellClasses; ?>">
    <div class="onboarding-split">
      <div class="onboarding-illustration-panel">
        <div class="onboarding-illustration-stack">
          <div class="onboarding-illustration-inner">
          <?php onboarding_render_illustration($illustration); ?>
          </div>
          <?php onboarding_render_tips_carousel(); ?>
        </div>
      </div>
      <div class="onboarding-content-panel">
        <div class="onboarding-content-card w-full">
          <?php onboarding_render_progress_steps($currentStep, $status); ?>
          <div class="<?php echo $stepBodyClasses; ?>">
    <?php
}

function onboarding_shell_close(): void
{
    ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
    <?php
}

function onboarding_spinner_markup(string $sizeClass = 'w-6 h-6'): string
{
    $sizeClass = htmlspecialchars($sizeClass, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<span class="onboarding-btn-next__spinner" role="status">
    <svg aria-hidden="true" class="{$sizeClass} animate-spin text-white/30 fill-white" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
    </svg>
    <span class="sr-only">Wird geladen…</span>
</span>
HTML;
}

function onboarding_btn_next_classes(): string
{
    return 'onboarding-btn-next';
}

function onboarding_render_btn_next(string $type = 'submit'): void
{
    ?>
<button type="<?php echo htmlspecialchars($type); ?>" class="<?php echo onboarding_btn_next_classes(); ?>">
    <span class="onboarding-btn-next__label">Weiter</span>
    <span class="onboarding-btn-next__arrow" aria-hidden="true">
        <svg fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>
        </svg>
    </span>
    <?php echo onboarding_spinner_markup(); ?>
</button>
    <?php
}

function onboarding_floating_label_bg_class(): string
{
    return 'bg-gray-50 dark:bg-primary-50';
}

function onboarding_floating_input(string $id, string $label, array $opts = []): void
{
    $name = array_key_exists('name', $opts) ? $opts['name'] : $id;
    $type = $opts['type'] ?? 'text';
    $value = htmlspecialchars((string) ($opts['value'] ?? ''), ENT_QUOTES, 'UTF-8');
    $required = !empty($opts['required']) ? ' required' : '';
    $labelText = htmlspecialchars($label . (!empty($opts['required']) ? ' *' : ''), ENT_QUOTES, 'UTF-8');
    $bg = onboarding_floating_label_bg_class();
    $inputClass = 'block px-3 pb-2 pt-3 w-full text-lg text-gray-900 dark:text-white bg-transparent rounded-lg border border-gray-300 dark:border-gray-600 appearance-none focus:outline-none focus:ring-0 focus:border-primary-600 dark:focus:border-primary-500 peer';
    $labelClass = 'absolute text-base text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-4 scale-75 top-1.5 z-10 origin-[0] ' . $bg . ' px-2 peer-focus:px-2 peer-focus:text-primary-600 dark:peer-focus:text-primary-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-1.5 peer-focus:scale-75 peer-focus:-translate-y-4 start-1 pointer-events-none';
    $extraAttrs = '';
    if (!empty($opts['minlength'])) {
        $extraAttrs .= ' minlength="' . (int) $opts['minlength'] . '"';
    }
    if (!empty($opts['autocomplete'])) {
        $extraAttrs .= ' autocomplete="' . htmlspecialchars((string) $opts['autocomplete'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if (!empty($opts['attrs'])) {
        $extraAttrs .= ' ' . (string) $opts['attrs'];
    }
    $nameAttr = ($name !== '' && $name !== false) ? ' name="' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') . '"' : '';
    ?>
<div class="relative">
    <input type="<?php echo htmlspecialchars($type); ?>" id="<?php echo htmlspecialchars($id); ?>"<?php echo $nameAttr; ?> value="<?php echo $value; ?>" class="<?php echo $inputClass; ?>" placeholder=" "<?php echo $required . $extraAttrs; ?>>
    <label for="<?php echo htmlspecialchars($id); ?>" class="<?php echo $labelClass; ?>"><?php echo $labelText; ?></label>
</div>
    <?php
}

function onboarding_floating_textarea(string $id, string $label, array $opts = []): void
{
    $name = $opts['name'] ?? $id;
    $value = htmlspecialchars((string) ($opts['value'] ?? ''), ENT_QUOTES, 'UTF-8');
    $rows = (int) ($opts['rows'] ?? 2);
    $bg = onboarding_floating_label_bg_class();
    $inputClass = 'block px-3 pb-2 pt-3 w-full text-lg text-gray-900 dark:text-white bg-transparent rounded-lg border border-gray-300 dark:border-gray-600 appearance-none focus:outline-none focus:ring-0 focus:border-primary-600 dark:focus:border-primary-500 peer resize-none';
    $labelClass = 'absolute text-base text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-4 scale-75 top-1.5 z-10 origin-[0] ' . $bg . ' px-2 peer-focus:px-2 peer-focus:text-primary-600 dark:peer-focus:text-primary-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-1.5 peer-focus:scale-75 peer-focus:-translate-y-4 start-1 pointer-events-none';
    ?>
<div class="relative">
    <textarea id="<?php echo htmlspecialchars($id); ?>" name="<?php echo htmlspecialchars($name); ?>" rows="<?php echo $rows; ?>" class="<?php echo $inputClass; ?>" placeholder=" "><?php echo $value; ?></textarea>
    <label for="<?php echo htmlspecialchars($id); ?>" class="<?php echo $labelClass; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
</div>
    <?php
}

function onboarding_floating_select(string $id, string $label, array $options, array $opts = []): void
{
    $name = $opts['name'] ?? $id;
    $value = (string) ($opts['value'] ?? '');
    $required = !empty($opts['required']) ? ' required' : '';
    $bg = onboarding_floating_label_bg_class();
    $selectClass = 'block px-3 pb-2 pt-3 w-full text-lg text-gray-900 dark:text-white bg-transparent rounded-lg border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-0 focus:border-primary-600 dark:focus:border-primary-500';
    $labelClass = 'absolute text-base text-gray-500 dark:text-gray-400 -translate-y-4 scale-75 top-1.5 z-10 origin-[0] ' . $bg . ' px-2 start-1 pointer-events-none';
    ?>
<div class="relative">
    <select id="<?php echo htmlspecialchars($id); ?>" name="<?php echo htmlspecialchars($name); ?>" class="<?php echo $selectClass; ?>"<?php echo $required; ?>>
        <?php foreach ($options as $optValue => $optLabel): ?>
        <option value="<?php echo htmlspecialchars((string) $optValue); ?>"<?php echo ((string) $optValue === $value) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $optLabel); ?></option>
        <?php endforeach; ?>
    </select>
    <label for="<?php echo htmlspecialchars($id); ?>" class="<?php echo $labelClass; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
</div>
    <?php
}

function onboarding_anrede_options(): array
{
    return [
        '' => 'Keine Angabe',
        'herr' => 'Herr',
        'frau' => 'Frau',
        'divers' => 'Divers',
        'neutral' => 'Neutral',
    ];
}

function onboarding_anrede_display(string $value): string
{
    $options = onboarding_anrede_options();

    return $options[$value] ?? 'Keine Angabe';
}

function onboarding_profile_initials(string $vorname, string $nachname): string
{
    $v = mb_substr(trim($vorname), 0, 1, 'UTF-8');
    $n = mb_substr(trim($nachname), 0, 1, 'UTF-8');
    $initials = mb_strtoupper($v . $n, 'UTF-8');

    return $initials !== '' ? $initials : '?';
}

function onboarding_profile_display_name(string $anrede, string $vorname, string $nachname): string
{
    $name = trim(trim($vorname) . ' ' . trim($nachname));

    if ($anrede !== '') {
        return trim(onboarding_anrede_display($anrede) . ' ' . $name);
    }

    return $name;
}

function onboarding_render_choice_field(string $id, string $label, array $options, array $opts = []): void
{
    $name = $opts['name'] ?? $id;
    $value = (string) ($opts['value'] ?? '');
    $optional = !empty($opts['optional']);
    $emptyMobileLabel = $opts['empty_mobile_label'] ?? '— keine Angabe —';
    $emptyChipLabel = $opts['empty_chip_label'] ?? 'Keine';
    $hideLabel = !empty($opts['hide_label']);
    $ariaLabel = (string) ($opts['aria_label'] ?? $label);
    $bg = onboarding_floating_label_bg_class();
    $selectClass = 'block px-3 pb-2 pt-3 w-full text-lg text-gray-900 dark:text-white bg-transparent rounded-lg border border-gray-300 dark:border-gray-600 appearance-none focus:outline-none focus:ring-0 focus:border-primary-600 dark:focus:border-primary-500';
    $mobileLabelClass = 'absolute text-base text-gray-500 dark:text-gray-400 -translate-y-4 scale-75 top-1.5 z-10 origin-[0] ' . $bg . ' px-2 start-1 pointer-events-none';
    $labelId = htmlspecialchars($id . '-label', ENT_QUOTES, 'UTF-8');
    $optionalSuffix = $optional ? ' <span class="onboarding-choice-optional">(optional)</span>' : '';
    ?>
<div data-onboarding-choice>
    <input type="hidden" id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="onboarding-choice-mobile sm:hidden">
        <div class="relative">
            <select id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>-mobile" class="<?php echo $selectClass; ?>" data-choice-mobile aria-label="<?php echo htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ($options as $optValue => $optLabel): ?>
                <option value="<?php echo htmlspecialchars((string) $optValue, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ((string) $optValue === $value) ? ' selected' : ''; ?>><?php echo htmlspecialchars($optValue === '' ? $emptyMobileLabel : (string) $optLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!$hideLabel): ?>
            <label for="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>-mobile" class="<?php echo $mobileLabelClass; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
            <?php endif; ?>
        </div>
    </div>

    <div class="onboarding-choice-desktop hidden sm:block onboarding-choice-field">
        <?php if (!$hideLabel): ?>
        <span class="onboarding-choice-field__label" id="<?php echo $labelId; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?><?php echo $optionalSuffix; ?></span>
        <?php endif; ?>
        <div class="onboarding-choice-chips" role="radiogroup"<?php echo $hideLabel ? ' aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '"' : ' aria-labelledby="' . $labelId . '"'; ?>>
            <?php foreach ($options as $optValue => $optLabel): ?>
            <button type="button"
                    class="onboarding-choice-chip<?php echo ((string) $optValue === $value) ? ' is-selected' : ''; ?>"
                    data-value="<?php echo htmlspecialchars((string) $optValue, ENT_QUOTES, 'UTF-8'); ?>"
                    role="radio"
                    aria-checked="<?php echo ((string) $optValue === $value) ? 'true' : 'false'; ?>">
                <?php echo htmlspecialchars($optValue === '' ? $emptyChipLabel : (string) $optLabel, ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>
    <?php
}

function onboarding_render_anrede_field(string $value = ''): void
{
    onboarding_render_choice_field('anrede', 'Anrede', onboarding_anrede_options(), [
        'value' => $value,
        'optional' => true,
    ]);
}

function onboarding_render_erreichbarkeit_field(string $value = ''): void
{
    user_profile_fields_render_erreichbarkeit_field($value, ['variant' => 'onboarding']);
}

function onboarding_load_company_for_user(PDO $pdo, int $userId): ?array
{
    static $encryptionLoaded = false;
    if (!$encryptionLoaded) {
        require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';
        $encryptionLoaded = true;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT u.company_id,
                   c.name AS company_name, c.email AS company_email,
                   c.telefonnummer AS company_telefon, c.adresse AS company_adresse,
                   c.plz AS company_plz, c.ort AS company_ort, c.kundennummer AS company_kundennummer,
                   assigned_user.vorname AS assigned_user_vorname,
                   assigned_user.nachname AS assigned_user_nachname
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN users assigned_user ON c.zugewiesen_an = assigned_user.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['company_id'])) {
            return null;
        }

        $companyRow = [
            'name' => $row['company_name'] ?? null,
            'email' => $row['company_email'] ?? null,
            'telefonnummer' => $row['company_telefon'] ?? null,
            'adresse' => $row['company_adresse'] ?? null,
            'plz' => $row['company_plz'] ?? null,
            'ort' => $row['company_ort'] ?? null,
            'kundennummer' => $row['company_kundennummer'] ?? null,
        ];
        decrypt_company_row($companyRow);

        $assignedName = trim(($row['assigned_user_vorname'] ?? '') . ' ' . ($row['assigned_user_nachname'] ?? ''));

        return [
            'name' => $companyRow['name'] ?? '',
            'kundennummer' => $companyRow['kundennummer'] ?? '',
            'email' => $companyRow['email'] ?? '',
            'telefon' => $companyRow['telefonnummer'] ?? '',
            'adresse' => trim($companyRow['adresse'] ?? ''),
            'ort_line' => trim(trim($companyRow['plz'] ?? '') . ' ' . trim($companyRow['ort'] ?? '')),
            'assigned_name' => $assignedName,
        ];
    } catch (PDOException $e) {
        error_log('onboarding_load_company_for_user: ' . $e->getMessage());
        return null;
    }
}

function onboarding_status_from_user(?array $user): array
{
    if (!$user) {
        return [
            'step1_completed' => false,
            'step2_completed' => false,
            'step3_completed' => false,
            'step4_completed' => false,
        ];
    }

    return [
        'step1_completed' => !empty($user['letztes_pw_change']),
        'step2_completed' => !empty($_SESSION['onboarding_profile_step_seen']),
        'step3_completed' => !empty($_SESSION['onboarding_contact_step_seen']),
        'step4_completed' => !empty($user['logopfad']) || !empty($_SESSION['onboarding_avatar_step_seen']),
    ];
}
