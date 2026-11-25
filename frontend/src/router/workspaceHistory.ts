import { parseQuery, stringifyQuery } from 'vue-router';
import type { HistoryState, LocationQuery, RouterHistory } from 'vue-router';

const contextKeys = ['objectId', 'spaceId', 'unityProviderId', 'previousContractId'];

function splitLocation(location: string) {
  const [address, fragment = ''] = location.split(/#(.*)/s);
  const [path, search = ''] = address.split(/\?(.*)/s);
  const query = parseQuery(search);
  const context: LocationQuery = {};
  const keys = path === '/login' ? ['redirect'] : contextKeys;

  for (const key of keys) {
    if (!(key in query)) continue;

    context[key] = query[key];
    delete query[key];
  }

  const stored = stringifyQuery(context);
  const visible = stringifyQuery(query);
  const url = path + (visible ? '?' + visible : '') + (fragment ? '#' + fragment : '');

  return { url: stored ? url : location, stored };
}

export function workspaceHistory(history: RouterHistory): RouterHistory {
  function currentLocation() {
    const stored = history.state.workspaceQuery;

    if (typeof stored !== 'string' || !stored) return history.location;

    const [address, fragment = ''] = history.location.split(/#(.*)/s);

    return (
      address + (address.includes('?') ? '&' : '?') + stored + (fragment ? '#' + fragment : '')
    );
  }

  function write(location: string, state: HistoryState | undefined, replace: boolean) {
    const { url, stored } = splitLocation(location);
    const samePage = history.location.split(/[?#]/, 1)[0] === url.split(/[?#]/, 1)[0];
    const returnPages = replace && samePage ? history.state.returnPages : null;
    const data = { returnPages, ...state, workspaceQuery: stored || null };

    if (replace) history.replace(url, data);
    else history.push(url, data);
  }

  return {
    ...history,
    get state() {
      return history.state;
    },
    get location() {
      return currentLocation();
    },
    createHref: (location) => history.createHref(splitLocation(location).url),
    push: (location, state) => write(location, state, false),
    replace: (location, state) => write(location, state, true),
    listen: (listener) =>
      history.listen((_to, from, info) => listener(currentLocation(), from, info)),
  };
}
