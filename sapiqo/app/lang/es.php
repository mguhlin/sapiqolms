<?php
// Spanish (es) UI strings. Keys mirror the English defaults passed to t().
// Only UI chrome is translated — course content, names, and org data stay as authored.

return [
    // Navigation / chrome
    'nav.dashboard'      => 'Panel',
    'nav.catalog'        => 'Catálogo',
    'nav.admin'          => 'Administración',
    'nav.manage'         => 'Gestionar',
    'nav.transcript'     => 'Expediente',
    'nav.profile'        => 'Perfil',
    'nav.signout'        => 'Cerrar sesión',
    'nav.signin'         => 'Iniciar sesión',
    'nav.register'       => 'Registrarse',
    'nav.notifications'  => 'Notificaciones',
    'footer.privacy'     => 'Términos de servicio y política de privacidad',

    // Common
    'common.back_home'   => 'Volver al inicio',
    'common.language'    => 'Idioma',

    // Splash / marketing
    'splash.eyebrow'     => 'Aprendizaje profesional',
    'splash.hero_sub'    => 'Cursos prácticos y alineados a estándares de',
    'splash.hero_sub2'   => 'Inicia sesión para inscribirte, seguir tu progreso y obtener una insignia y un certificado digital.',
    'splash.browse'      => 'Ver cursos',
    'splash.create'      => 'Crear una cuenta',
    'splash.signin'      => 'Iniciar sesión',
    'splash.available'   => 'Cursos disponibles',
    'splash.available_sub' => 'Inicia sesión para inscribirte. Tu progreso e insignias se guardan en tu cuenta.',
    'splash.none'        => 'No hay cursos disponibles por ahora. Vuelve pronto.',
    'splash.start'       => 'Inicia sesión para comenzar →',
    'splash.overview'    => 'Ver descripción →',
    'splash.lessons'     => 'lecciones',
    'splash.videos'      => 'videos',

    // Auth
    'auth.welcome'       => 'Bienvenido de nuevo',
    'auth.signin_sub'    => 'Inicia sesión para continuar tus cursos y ver tus insignias.',
    'auth.email'         => 'Correo electrónico',
    'auth.password'      => 'Contraseña',
    'auth.signin_btn'    => 'Iniciar sesión',
    'auth.forgot'        => '¿Olvidaste tu contraseña?',
    'auth.new_here'      => '¿Nuevo aquí?',
    'auth.create_account'=> 'Crear una cuenta',
    'auth.continue_with' => 'Continuar con',
    'auth.or_email'      => 'o usa tu correo electrónico',

    // Register
    'register.title'     => 'Crea tu cuenta',
    'register.first'     => 'Nombre',
    'register.last'      => 'Apellido',
    'register.create_btn'=> 'Crear cuenta',
    'register.have'      => '¿Ya tienes una cuenta?',

    // Dashboard
    'dash.hello'         => 'Hola',
    'dash.intro'        => 'Sigue tu progreso, retoma un curso y descarga las insignias que has obtenido.',
    'dash.your_courses'  => 'Tus cursos',
    'dash.none'          => 'Aún no estás inscrito en ningún curso.',
    'dash.browse'        => 'Explora el catálogo',
    'dash.complete'      => 'completado',
    'dash.completed'     => 'Completado',
    'dash.in_progress'   => 'En curso',
    'dash.unavailable'   => 'No disponible',
    'dash.resume'        => 'Retomar',
    'dash.start'         => 'Comenzar',
    'dash.review'        => 'Repasar',
    'dash.last_active'   => 'Última actividad',
    'dash.your_badges'   => 'Tus insignias',
    'dash.no_badges'     => 'Completa un curso para obtener tu primera insignia.',
    'dash.badge_png'     => 'Insignia (PNG)',
    'dash.cert_pdf'      => 'Certificado (PDF)',
    'dash.view_transcript' => 'Ver expediente →',

    // Transcript
    'transcript.title'     => 'Expediente académico',
    'transcript.intro'     => 'Tus cursos completados, horas CPE obtenidas e insignias.',
    'transcript.print'     => 'Imprimir / Guardar como PDF',
    'transcript.learner'   => 'Participante',
    'transcript.completed' => 'Cursos completados',
    'transcript.cpe'       => 'Horas CPE',
    'transcript.none'      => 'Aún no has completado cursos. Termina un curso para obtener una insignia y horas CPE.',
    'transcript.browse'    => 'Explorar el catálogo',
    'transcript.badge'     => 'Insignia',
    'transcript.course'    => 'Curso',
    'transcript.date'      => 'Completado',
    'transcript.credential'=> 'Credencial',
    'transcript.total'     => 'Total de horas CPE',
    'transcript.issued_by' => 'Emitido por',
    'transcript.verify'    => 'Cada credencial se puede verificar con su código en el certificado.',

    // Catalog
    'catalog.title'      => 'Catálogo de cursos',
    'catalog.intro'      => 'Inscríbete y empieza a aprender. Tu progreso se guarda en tu cuenta.',
    'catalog.none'       => 'Aún no hay cursos disponibles.',
    'catalog.steps'      => 'pasos',
    'catalog.enrolled'   => 'Inscrito',
    'catalog.continue'   => 'Continuar',
    'catalog.enroll'     => 'Inscribirse y empezar',
    'catalog.requires'   => 'Requiere',
    'catalog.start_prereq'=> 'Comenzar prerrequisito',

    // Profile
    'profile.title'      => 'Tu perfil',
    'profile.intro'      => 'Actualiza tu foto, nombre, datos de contacto y contraseña.',
    'profile.photo'      => 'Foto de perfil',
    'profile.upload'     => 'Subir foto',
    'profile.remove'     => 'Quitar',
    'profile.save'       => 'Guardar cambios',

    // Notifications
    'notif.title'        => 'Notificaciones',
    'notif.intro'        => 'Tu actividad y novedades recientes.',
    'notif.none'         => 'Estás al día — aún no hay notificaciones.',

    // Password reset
    'forgot.title'       => 'Restablecer tu contraseña',
    'forgot.intro'       => 'Ingresa el correo de tu cuenta y te enviaremos un enlace para crear una nueva contraseña.',
    'forgot.send'        => 'Enviar enlace',
    'forgot.back'        => 'Volver a iniciar sesión',
    'reset.title'        => 'Elige una nueva contraseña',
    'reset.new'          => 'Nueva contraseña',
    'reset.confirm'      => 'Confirmar nueva contraseña',
    'reset.set'          => 'Establecer contraseña',

    // Help center (page chrome; the manual pages themselves live in docs/manual/es/)
    'help.title'         => 'Ayuda y guía del usuario',
    'help.subtitle'      => 'Guías paso a paso de cada función. Se muestran las páginas correspondientes a tu rol.',
    'help.intro_learner' => 'Todo lo que necesitas como estudiante: encontrar y tomar cursos, completar cuestionarios, obtener insignias digitales y certificados, canjear un código de inscripción, participar en los foros de discusión y mantener un expediente permanente de lo que has terminado.',
    'help.intro_dev'     => 'Como desarrollador de cursos, encontrarás guías para crear cursos en el editor visual de bloques, gestionar tu centro de recursos y el libro de calificaciones, y configurar los ajustes del curso (horas CPE y GT, requisitos previos, vencimiento, foros), además de todo lo que ve un estudiante.',
    'help.intro_admin'   => 'Como administrador, esta guía cubre toda la plataforma: cuentas y roles, organizaciones y grupos, certificaciones, códigos de inscripción, importaciones y exportaciones, copias de seguridad, informes, personalización de marca e integraciones (SSO, LTI, API, actualizaciones), además de las guías de creación de cursos y para estudiantes.',
    'help.role_learner'  => 'estudiante',
    'help.role_dev'      => 'desarrollador de cursos',
    'help.role_admin'    => 'administrador',
    'help.welcome'       => 'Bienvenido a la Ayuda',
    'help.signed_in_as'  => 'Has iniciado sesión como',
    'help.browse_topics' => 'Explora los temas a continuación: solo se muestran los relevantes para tu nivel de acceso.',
    'help.cant_find'     => '¿No encuentras lo que buscas?',
    'help.contact_us'    => 'Contáctanos',
    'help.all_topics'    => 'Todos los temas de ayuda',
    'help.topics'        => 'Temas',
    'help.sec_learners'  => 'Para estudiantes',
    'help.sec_devs'      => 'Para desarrolladores de cursos',
    'help.sec_people'    => 'Administración — personas y acceso',
    'help.sec_courses'   => 'Administración — cursos y datos',
    'help.sec_platform'  => 'Administración — plataforma e integraciones',
];
