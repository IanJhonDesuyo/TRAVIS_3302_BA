import { Redirect } from 'expo-router';

// Backward-compatible route for old links and cached navigation state.
export default function LegacyLandingRoute() {
  return <Redirect href="/" />;
}
