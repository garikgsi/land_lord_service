import calendarUrl from '@tabler/core/dist/libs/vanilla-calendar-pro/index.js?url';

let calendarReady: Promise<void> | undefined;

export default async function useTablerDatepicker() {
  calendarReady ??= new Promise<void>((resolve, reject) => {
    const script = document.createElement('script');

    script.src = calendarUrl;
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => {
      script.remove();
      calendarReady = undefined;
      reject(new Error('Не удалось загрузить календарь.'));
    };

    document.head.append(script);
  });

  await calendarReady;

  return (await import('@tabler/core')).Datepicker;
}
