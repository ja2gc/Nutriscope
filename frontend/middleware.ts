import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

export function middleware(request: NextRequest) {
  const token = request.cookies.get("nutriscope_token")?.value;
  const { pathname } = request.nextUrl;

  // Define public paths that don't need authentication
  const isPublicPath = pathname === "/login";
  
  // Exclude Next.js internals, static files, and api routes
  const isInternalOrStatic = 
    pathname.startsWith("/_next") || 
    pathname.startsWith("/api") || 
    pathname.includes("."); // files like favicon.ico, images

  if (isInternalOrStatic) {
    return NextResponse.next();
  }

  // If user is authenticated and trying to access /login, redirect to /dashboard
  if (isPublicPath && token) {
    return NextResponse.redirect(new URL("/dashboard", request.url));
  }

  // If user is unauthenticated and trying to access any other page, redirect to /login
  if (!isPublicPath && !token) {
    return NextResponse.redirect(new URL("/login", request.url));
  }

  return NextResponse.next();
}

// Configure paths that will trigger this middleware
export const config = {
  matcher: [
    /*
     * Match all request paths except for the ones starting with:
     * - api (API routes)
     * - _next/static (static files)
     * - _next/image (image optimization files)
     * - favicon.ico (favicon file)
     */
    "/((?!api|_next/static|_next/image|favicon.ico).*)",
  ],
};
