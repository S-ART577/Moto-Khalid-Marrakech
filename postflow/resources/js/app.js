/*
 * PostFlow front-end helpers. Livewire already ships Alpine, so this file only
 * adds small browser features used by the screens.
 */

// Copy text to the clipboard (caption, hashtags). Returns true when it worked.
window.pfCopy = async (text) => {
    try {
        await navigator.clipboard.writeText(text ?? '')
        return true
    } catch (e) {
        // Fallback for older phones
        let area = document.createElement('textarea')
        area.value = text ?? ''
        area.setAttribute('readonly', '')
        area.style.position = 'fixed'
        area.style.opacity = '0'
        document.body.appendChild(area)
        area.select()
        let ok = document.execCommand('copy')
        area.remove()
        return ok
    }
}

// Paste the clipboard into an input (the post link after posting). The browser may ask for permission.
window.pfPaste = async (input) => {
    try {
        input.value = (await navigator.clipboard.readText()).trim()
        input.dispatchEvent(new Event('input'))
    } catch (e) {
        input.focus()
    }
}

// Save an element as a PNG image (calendar, radar...). The library is loaded only when needed.
// We render a cleaned copy: attributes like x-on:click or wire:key are not valid in the SVG
// the library builds, and would make the export fail.
window.pfExportPng = async (element, filename) => {
    let { toPng } = await import('html-to-image')
    let background = getComputedStyle(document.documentElement).getPropertyValue('--pf-bg').trim() || '#102533'

    let clone = element.cloneNode(true)
    for (let el of [clone, ...clone.querySelectorAll('*')]) {
        for (let attribute of [...el.attributes]) {
            if (/^(x-|wire:|@|:)/.test(attribute.name) || attribute.name.includes(':')) el.removeAttribute(attribute.name)
        }
    }
    clone.querySelectorAll('.no-print, template').forEach((el) => el.remove())

    let holder = document.createElement('div')
    holder.style.cssText = `position:fixed;left:-100000px;top:0;width:${element.offsetWidth}px`
    holder.appendChild(clone)
    document.body.appendChild(holder)

    try {
        let dataUrl = await toPng(clone, { pixelRatio: 2, backgroundColor: background, cacheBust: true })
        let link = document.createElement('a')
        link.download = filename
        link.href = dataUrl
        link.click()
    } finally {
        holder.remove()
    }
}

// Browser push: ask permission, subscribe, send the subscription to the server.
window.pfEnablePush = async (vapidPublicKey, url) => {
    if (! ('serviceWorker' in navigator) || ! ('PushManager' in window)) return 'unsupported'

    let permission = await Notification.requestPermission()
    if (permission !== 'granted') return 'denied'

    let registration = await navigator.serviceWorker.ready
    let subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    })

    await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
            Accept: 'application/json',
        },
        body: JSON.stringify(subscription),
    })

    return 'on'
}

function urlBase64ToUint8Array(base64String) {
    let padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    let base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
    let raw = atob(base64)
    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)))
}

// Installable app + offline page + push notifications.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}))
}
